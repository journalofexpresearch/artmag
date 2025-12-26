<?php
require_once __DIR__ . '/math_main.php';

/**
 * Circuit Analysis Module
 * 
 * Implements Kirchhoff's laws, Ohm's law, impedance calculations,
 * and circuit solving for electromagnetic simulations.
 */

class Complex {
    public function __construct(
        public float $real = 0.0,
        public float $imag = 0.0
    ) {}
    
    public function add(Complex $b): Complex {
        return new Complex($this->real + $b->real, $this->imag + $b->imag);
    }
    
    public function subtract(Complex $b): Complex {
        return new Complex($this->real - $b->real, $this->imag - $b->imag);
    }
    
    public function multiply(Complex $b): Complex {
        return new Complex(
            $this->real * $b->real - $this->imag * $b->imag,
            $this->real * $b->imag + $this->imag * $b->real
        );
    }
    
    public function divide(Complex $b): Complex {
        $denom = $b->real**2 + $b->imag**2;
        if (abs($denom) < 1e-15) return new Complex(PHP_FLOAT_MAX, 0);

        return new Complex(
            ($this->real * $b->real + $this->imag * $b->imag) / $denom,
            ($this->imag * $b->real - $this->real * $b->imag) / $denom
        );
    }
    
    public function magnitude(): float {
        return sqrt($this->real**2 + $this->imag**2);
    }
    
    public function phase(): float {
        return atan2($this->imag, $this->real);
    }
    
    public function conjugate(): Complex {
        return new Complex($this->real, -$this->imag);
    }
    
    public static function fromPolar(float $magnitude, float $phase): Complex {
        return new Complex(
            $magnitude * cos($phase),
            $magnitude * sin($phase)
        );
    }
    
    public function inverse(): Complex {
        $one = new Complex(1, 0);
        return $one->divide($this);
    }
}

class CircuitNode {
    public function __construct(
        public string $id,
        public float $voltage = 0.0,
        public array $connections = []
    ) {}
}

class CircuitBranch {
    public function __construct(
        public string $id,
        public string $startNode,
        public string $endNode,
        public float $current = 0.0,
        public Complex $impedance,
        public string $type,  // 'resistor', 'capacitor', 'inductor', 'wire', 'source'
        public float $value   // Resistance, capacitance, inductance, or voltage
    ) {}
}

class Impedance {
    
    /**
     * Calculate resistor impedance: Z = R
     */
    public static function resistor(float $resistance): Complex {
        $minR = SIMULATION_LIMITS['minResistance'];
        return new Complex(max($resistance, $minR), 0);
    }
    
    /**
     * Calculate capacitor impedance: Z = -j/(ωC)
     */
    public static function capacitor(float $capacitance, float $frequency): Complex {
        if (abs($frequency) < 1e-15) {
            return new Complex(PHP_FLOAT_MAX, 0); // Open circuit for DC
        }
        $omega = 2 * M_PI * $frequency;
        return new Complex(0, -1 / ($omega * $capacitance));
    }
    
    /**
     * Calculate inductor impedance: Z = jωL
     */
    public static function inductor(float $inductance, float $frequency): Complex {
        $omega = 2 * M_PI * $frequency;
        return new Complex(0, $omega * $inductance);
    }
    
    /**
     * Calculate series impedance
     */
    public static function series(array $impedances): Complex {
        $total = new Complex(0, 0);
        foreach ($impedances as $z) {
            $total = $total->add($z);
        }
        return $total;
    }
    
    /**
     * Calculate parallel impedance: 1/Z_total = 1/Z1 + 1/Z2 + ...
     */
    public static function parallel(array $impedances): Complex {
        $totalAdmittance = new Complex(0, 0);
        
        foreach ($impedances as $z) {
            $admittance = $z->inverse();
            $totalAdmittance = $totalAdmittance->add($admittance);
        }
        
        return $totalAdmittance->inverse();
    }
    
    /**
     * Calculate wire resistance: R = ρL/A
     * Includes temperature coefficient
     */
    public static function wireResistance(
        float $length,
        float $crossSection,
        string $material = 'copper',
        float $temperature = 20.0
    ): float {
        $props = MATERIAL_PROPERTIES[$material];
        $tempFactor = 1 + $props['thermalCoefficient'] * ($temperature - 20);
        return ($props['resistivity'] * $length * $tempFactor) / $crossSection;
    }
}

class CircuitAnalysis {
    
    /**
     * Calculate power dissipation: P = I²R
     */
    public static function powerDissipation(float $current, float $resistance): float {
        return $current**2 * $resistance;
    }
    
    /**
     * Calculate temperature rise from power dissipation
     */
    public static function temperatureRise(
        float $power,
        float $thermalResistance = 50.0,  // °C/W
        float $ambientTemp = 25.0
    ): float {
        return $ambientTemp + $power * $thermalResistance;
    }
    
    /**
     * Check if circuit has a complete path (closed loop)
     */
    public static function isCircuitComplete(
        array $nodes,
        array $branches
    ): bool {
        if (empty($nodes) || empty($branches)) return false;
        
        // Find source nodes
        $sourceNodes = [];
        foreach ($branches as $branch) {
            if ($branch->type === 'source') {
                $sourceNodes[] = $branch->startNode;
                $sourceNodes[] = $branch->endNode;
            }
        }
        
        if (empty($sourceNodes)) return false;
        
        // BFS to check connectivity
        $visited = [];
        $queue = [$nodes[0]->id];
        
        while (!empty($queue)) {
            $nodeId = array_shift($queue);
            if (in_array($nodeId, $visited)) continue;
            $visited[] = $nodeId;
            
            // Find connected nodes through branches
            foreach ($branches as $branch) {
                if ($branch->startNode === $nodeId && !in_array($branch->endNode, $visited)) {
                    $queue[] = $branch->endNode;
                }
                if ($branch->endNode === $nodeId && !in_array($branch->startNode, $visited)) {
                    $queue[] = $branch->startNode;
                }
            }
        }
        
        // Check if all nodes visited and connected to source
        $hasSource = false;
        foreach ($visited as $id) {
            if (in_array($id, $sourceNodes)) {
                $hasSource = true;
                break;
            }
        }
        
        return count($visited) === count($nodes) && $hasSource;
    }
    
    /**
     * Simple nodal analysis for DC circuits
     * Uses iterative Gauss-Seidel solver
     * Returns associative array of node voltages
     */
    public static function nodalAnalysis(
        array $nodes,
        array $branches,
        string $groundNodeId
    ): array {
        $voltages = [];
        
        // Set ground node to 0V
        $voltages[$groundNodeId] = 0.0;
        
        // Find voltage sources and set their connected nodes
        foreach ($branches as $branch) {
            if ($branch->type === 'source') {
                if ($branch->startNode === $groundNodeId) {
                    $voltages[$branch->endNode] = $branch->value;
                } else if ($branch->endNode === $groundNodeId) {
                    $voltages[$branch->startNode] = $branch->value;
                }
            }
        }
        
        // Iterative solver (Gauss-Seidel)
        $maxIterations = 100;
        $tolerance = 1e-6;
        $minR = SIMULATION_LIMITS['minResistance'];
        
        for ($iter = 0; $iter < $maxIterations; $iter++) {
            $maxChange = 0.0;
            
            foreach ($nodes as $node) {
                // Skip ground and voltage source nodes
                if ($node->id === $groundNodeId || isset($voltages[$node->id])) {
                    continue;
                }
                
                $sumConductance = 0.0;
                $sumCurrents = 0.0;
                
                // Sum contributions from connected branches
                foreach ($branches as $branch) {
                    if ($branch->startNode === $node->id || $branch->endNode === $node->id) {
                        $otherNodeId = $branch->startNode === $node->id 
                            ? $branch->endNode 
                            : $branch->startNode;
                        
                        $otherVoltage = $voltages[$otherNodeId] ?? 0.0;
                        
                        if ($branch->type !== 'source') {
                            $conductance = 1 / max($branch->impedance->real, $minR);
                            $sumConductance += $conductance;
                            $sumCurrents += $conductance * $otherVoltage;
                        }
                    }
                }
                
                if ($sumConductance > 0) {
                    $newVoltage = $sumCurrents / $sumConductance;
                    $oldVoltage = $voltages[$node->id] ?? 0.0;
                    $maxChange = max($maxChange, abs($newVoltage - $oldVoltage));
                    $voltages[$node->id] = $newVoltage;
                }
            }
            
            // Check convergence
            if ($maxChange < $tolerance) break;
        }
        
        return $voltages;
    }
    
    /**
     * Calculate branch currents from node voltages
     */
    public static function calculateBranchCurrents(
        array $branches,
        array $voltages
    ): array {
        $currents = [];
        $minR = SIMULATION_LIMITS['minResistance'];
        
        foreach ($branches as $branch) {
            $v1 = $voltages[$branch->startNode] ?? 0.0;
            $v2 = $voltages[$branch->endNode] ?? 0.0;
            $deltaV = $v1 - $v2;
            
            if ($branch->type === 'source') {
                // For voltage sources, current is determined by the circuit
                // Would need full analysis to determine this properly
                $currents[$branch->id] = 0.0; // Placeholder
            } else {
                $impedanceMag = $branch->impedance->magnitude();
                $current = $deltaV / max($impedanceMag, $minR);
                $currents[$branch->id] = $current;
            }
        }
        
        return $currents;
    }
    
    /**
     * Calculate total power in circuit
     */
    public static function totalPower(
        array $branches,
        array $currents
    ): float {
        $totalPower = 0.0;
        
        foreach ($branches as $branch) {
            $current = $currents[$branch->id] ?? 0.0;
            if ($branch->type !== 'source') {
                $resistance = $branch->impedance->real;
                $totalPower += self::powerDissipation($current, $resistance);
            }
        }
        
        return $totalPower;
    }
    
    /**
     * AC circuit analysis with phasors
     * Returns complex voltages and currents
     */
    public static function acNodalAnalysis(
        array $nodes,
        array $branches,
        string $groundNodeId,
        float $frequency
    ): array {
        // This would implement complex nodal analysis for AC circuits
        // Using matrix methods (would need matrix library or implement)
        // Placeholder for now - full implementation would use
        // Modified Nodal Analysis (MNA) with complex numbers
        
        return [
            'voltages' => [],
            'currents' => [],
            'frequency' => $frequency
        ];
    }
    
    /**
     * Kirchhoff's Current Law verification
     * Sum of currents at node should be zero
     */
    public static function verifyKCL(
        string $nodeId,
        array $branches,
        array $currents
    ): float {
        $sum = 0.0;
        
        foreach ($branches as $branch) {
            $current = $currents[$branch->id] ?? 0.0;
            
            if ($branch->startNode === $nodeId) {
                $sum -= $current; // Current leaving node
            }
            if ($branch->endNode === $nodeId) {
                $sum += $current; // Current entering node
            }
        }
        
        return $sum; // Should be close to zero
    }
    
    /**
     * Kirchhoff's Voltage Law verification
     * Sum of voltages around loop should be zero
     */
    public static function verifyKVL(
        array $loopPath,  // Array of node IDs forming a loop
        array $voltages
    ): float {
        $sum = 0.0;
        
        for ($i = 0; $i < count($loopPath) - 1; $i++) {
            $v1 = $voltages[$loopPath[$i]] ?? 0.0;
            $v2 = $voltages[$loopPath[$i + 1]] ?? 0.0;
            $sum += $v1 - $v2;
        }
        
        return $sum; // Should be close to zero
    }
}

class CircuitMetrics {
    
    /**
     * Calculate circuit efficiency
     */
    public static function efficiency(
        float $outputPower,
        float $inputPower
    ): float {
        if (abs($inputPower) < 1e-15) return 0.0;
        return ($outputPower / $inputPower) * 100; // Percentage
    }
    
    /**
     * Calculate voltage regulation
     */
    public static function voltageRegulation(
        float $noLoadVoltage,
        float $fullLoadVoltage
    ): float {
        if (abs($fullLoadVoltage) < 1e-15) return 0.0;
        return (($noLoadVoltage - $fullLoadVoltage) / $fullLoadVoltage) * 100;
    }
    
    /**
     * Calculate quality factor (Q) for resonant circuits
     */
    public static function qualityFactor(
        float $inductance,
        float $resistance,
        float $frequency
    ): float {
        $omega = 2 * M_PI * $frequency;
        return ($omega * $inductance) / $resistance;
    }
    
    /**
     * Calculate resonant frequency for LC circuit
     */
    public static function resonantFrequency(
        float $inductance,
        float $capacitance
    ): float {
        return 1 / (2 * M_PI * sqrt($inductance * $capacitance));
    }
    
    /**
     * Calculate time constant for RC circuit
     */
    public static function rcTimeConstant(
        float $resistance,
        float $capacitance
    ): float {
        return $resistance * $capacitance;
    }
    
    /**
     * Calculate time constant for RL circuit
     */
    public static function rlTimeConstant(
        float $resistance,
        float $inductance
    ): float {
        return $inductance / $resistance;
    }
}
