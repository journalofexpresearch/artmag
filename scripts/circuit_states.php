<?php
require_once __DIR__ . '/circuit_parts.php';
require_once __DIR__ . '/circuit_analysis.php';
require_once __DIR__ . '/physics.php';

/**
 * Circuit State Management
 * 
 * Handles circuit project state, validation, simulation control,
 * and persistence. PHP equivalent of the Zustand store.
 */

class SimulationState {
    public function __construct(
        public bool $isRunning = false,
        public float $time = 0.0,
        public bool $isCircuitComplete = false,
        public array $errors = [],
        public array $warnings = []
    ) {}
}

class CircuitState {
    public CircuitProject $project;
    public ?string $selectedComponentId = null;
    public ?string $selectedWireId = null;
    public ?string $hoveredComponentId = null;
    public SimulationState $simulation;
    public string $viewMode = 'schematic'; // 'schematic', 'field3d', 'largescale'
    
    public function __construct(?CircuitProject $project = null) {
        $this->project = $project ?? ComponentFactory::createProject('New Circuit');
        $this->simulation = new SimulationState();
    }
    
    // ==================== Component Actions ====================
    
    /**
     * Add component to circuit
     */
    public function addComponent(string $type, array $position): ?CircuitComponent {
        $component = ComponentFactory::create($type, $position);
        if (!$component) return null;
        
        $this->project->components[] = $component;
        $this->project->updatedAt = new DateTime();
        
        return $component;
    }
    
    /**
     * Remove component and connected wires
     */
    public function removeComponent(string $id): bool {
        $found = false;
        
        // Remove component
        $this->project->components = array_filter(
            $this->project->components,
            function($c) use ($id, &$found) {
                if ($c->id === $id) {
                    $found = true;
                    return false;
                }
                return true;
            }
        );
        
        // Remove connected wires
        $this->project->wires = array_filter(
            $this->project->wires,
            fn($w) => $w->startComponentId !== $id && $w->endComponentId !== $id
        );
        
        // Clear selection if deleted
        if ($this->selectedComponentId === $id) {
            $this->selectedComponentId = null;
        }
        
        $this->project->updatedAt = new DateTime();
        return $found;
    }
    
    /**
     * Update component properties
     */
    public function updateComponent(string $id, array $updates): bool {
        foreach ($this->project->components as &$component) {
            if ($component->id === $id) {
                // Update allowed properties
                foreach ($updates as $key => $value) {
                    if (property_exists($component, $key)) {
                        $component->$key = $value;
                    } elseif (property_exists($component->properties, $key)) {
                        $component->properties->$key = $value;
                    }
                }
                $this->project->updatedAt = new DateTime();
                return true;
            }
        }
        return false;
    }
    
    /**
     * Move component to new position
     */
    public function moveComponent(string $id, array $position): bool {
        foreach ($this->project->components as &$component) {
            if ($component->id === $id) {
                $component->position = $position;
                return true;
            }
        }
        return false;
    }
    
    /**
     * Rotate component
     */
    public function rotateComponent(string $id, float $rotation): bool {
        foreach ($this->project->components as &$component) {
            if ($component->id === $id) {
                $component->rotation = $rotation;
                return true;
            }
        }
        return false;
    }
    
    // ==================== Wire Actions ====================
    
    /**
     * Add wire connection
     */
    public function addWire(
        string $startComponentId,
        string $startPortId,
        string $endComponentId,
        string $endPortId
    ): Wire {
        $wire = ComponentFactory::createWire(
            $startPortId,
            $endPortId,
            $startComponentId,
            $endComponentId
        );
        
        $this->project->wires[] = $wire;
        $this->project->updatedAt = new DateTime();
        
        return $wire;
    }
    
    /**
     * Remove wire
     */
    public function removeWire(string $id): bool {
        $originalCount = count($this->project->wires);
        
        $this->project->wires = array_filter(
            $this->project->wires,
            fn($w) => $w->id !== $id
        );
        
        if ($this->selectedWireId === $id) {
            $this->selectedWireId = null;
        }
        
        $this->project->updatedAt = new DateTime();
        return count($this->project->wires) < $originalCount;
    }
    
    // ==================== Selection ====================
    
    public function selectComponent(?string $id): void {
        $this->selectedComponentId = $id;
        $this->selectedWireId = null;
    }
    
    public function selectWire(?string $id): void {
        $this->selectedWireId = $id;
        $this->selectedComponentId = null;
    }
    
    public function hoverComponent(?string $id): void {
        $this->hoveredComponentId = $id;
    }
    
    // ==================== Simulation ====================
    
    /**
     * Start simulation
     */
    public function startSimulation(): bool {
        $validation = $this->validateCircuit();
        
        $this->simulation->isRunning = $validation['isValid'];
        $this->simulation->errors = $validation['errors'];
        $this->simulation->warnings = $validation['warnings'];
        $this->simulation->isCircuitComplete = $validation['isValid'];
        
        return $validation['isValid'];
    }
    
    /**
     * Stop simulation
     */
    public function stopSimulation(): void {
        $this->simulation->isRunning = false;
    }
    
    /**
     * Step simulation forward by deltaTime
     */
    public function stepSimulation(float $deltaTime): void {
        if (!$this->simulation->isRunning) return;
        
        $settings = $this->project->settings;
        
        // Build circuit graph for analysis
        $nodes = [];
        $branches = [];
        
        // Create nodes from component ports
        foreach ($this->project->components as $comp) {
            foreach ($comp->ports as $port) {
                $nodes[] = new CircuitNode(
                    id: $port->id,
                    voltage: 0.0,
                    connections: []
                );
            }
        }
        
        // Create branches from components (2-port components)
        foreach ($this->project->components as $comp) {
            if (count($comp->ports) >= 2 && $comp->type !== 'ground') {
                $impedance = null;
                $branchType = 'resistor';
                $value = 0.0;

                switch ($comp->type) {
                    case 'resistor':
                        $value = $comp->properties->resistance ?? 1000;
                        $impedance = Impedance::resistor($value);
                        $branchType = 'resistor';
                        break;

                    case 'capacitor':
                        $value = $comp->properties->capacitance ?? 1e-6;
                        $frequency = $settings['frequency'] ?? 60;
                        $impedance = Impedance::capacitor($value, $frequency);
                        $branchType = 'capacitor';
                        break;

                    case 'inductor':
                    case 'coil':
                    case 'solenoid':
                        $value = $comp->properties->inductance ?? 1e-3;
                        $frequency = $settings['frequency'] ?? 60;
                        $impedance = Impedance::inductor($value, $frequency);
                        $branchType = 'inductor';
                        break;

                    case 'dcSource':
                        $value = $comp->properties->voltage ?? 12;
                        $impedance = Impedance::resistor(0.01); // Internal resistance
                        $branchType = 'source';
                        break;

                    case 'acSource':
                        $value = $comp->properties->voltage ?? 120;
                        $impedance = Impedance::resistor(0.01);
                        $branchType = 'source';
                        break;

                    case 'wire':
                        $value = 0.01;
                        $impedance = Impedance::resistor(0.01);
                        $branchType = 'wire';
                        break;

                    case 'switch':
                        if ($comp->properties->isOpen ?? true) {
                            $impedance = Impedance::resistor(1e12); // Open = very high resistance
                            $value = 1e12;
                        } else {
                            $impedance = Impedance::resistor(0.01); // Closed = low resistance
                            $value = 0.01;
                        }
                        $branchType = 'switch';
                        break;

                    default:
                        // Default: small resistance
                        $impedance = Impedance::resistor(0.01);
                        $value = 0.01;
                        $branchType = 'component';
                        break;
                }

                if ($impedance) {
                    $branches[] = new CircuitBranch(
                        id: $comp->id,
                        startNode: $comp->ports[0]->id,
                        endNode: $comp->ports[1]->id,
                        impedance: $impedance,
                        type: $branchType,
                        value: $value,
                        current: 0.0
                    );
                }
            }
        }

        // Create branches from wires
        foreach ($this->project->wires as $wire) {
            $branches[] = new CircuitBranch(
                id: $wire->id,
                startNode: $wire->startPortId,
                endNode: $wire->endPortId,
                impedance: Impedance::resistor(0.01), // Small wire resistance
                type: 'wire',
                value: 0.01,
                current: 0.0
            );
        }
        
        // Find ground node
        $groundComp = null;
        foreach ($this->project->components as $comp) {
            if ($comp->type === 'ground') {
                $groundComp = $comp;
                break;
            }
        }
        
        $groundNodeId = $groundComp?->ports[0]?->id ?? $nodes[0]?->id ?? null;
        
        if ($groundNodeId && !empty($nodes)) {
            // Calculate circuit voltages and currents
            $voltages = CircuitAnalysis::nodalAnalysis($nodes, $branches, $groundNodeId);
            $currents = CircuitAnalysis::calculateBranchCurrents($branches, $voltages);

            // Update component electrical states from analysis
            foreach ($this->project->components as &$comp) {
                // Find the branch for this component
                $branchCurrent = $currents[$comp->id] ?? 0.0;

                // Update current flow
                $comp->currentFlow = abs($branchCurrent);

                // Calculate voltage drop across component
                if (count($comp->ports) >= 2) {
                    $v1 = $voltages[$comp->ports[0]->id] ?? 0.0;
                    $v2 = $voltages[$comp->ports[1]->id] ?? 0.0;
                    $comp->voltageDrop = abs($v1 - $v2);
                }

                // Calculate power dissipation
                switch ($comp->type) {
                    case 'resistor':
                    case 'wire':
                        $resistance = $comp->properties->resistance ??
                                    ($comp->type === 'wire' ? 0.01 : 1000);
                        $comp->powerDissipation = CircuitAnalysis::powerDissipation(
                            $comp->currentFlow,
                            $resistance
                        );
                        break;

                    case 'dcSource':
                    case 'acSource':
                        // Power supplied by source (negative dissipation conceptually)
                        $comp->powerDissipation = abs($comp->currentFlow * ($comp->properties->voltage ?? 0));
                        break;

                    case 'inductor':
                    case 'coil':
                    case 'solenoid':
                        // Inductors have wire resistance
                        $wireResistance = 0.1; // Simplified
                        $comp->powerDissipation = CircuitAnalysis::powerDissipation(
                            $comp->currentFlow,
                            $wireResistance
                        );
                        break;

                    case 'capacitor':
                        // Ideal capacitors don't dissipate power, but ESR causes some
                        $esr = 0.01; // Equivalent Series Resistance
                        $comp->powerDissipation = CircuitAnalysis::powerDissipation(
                            $comp->currentFlow,
                            $esr
                        );
                        break;

                    default:
                        $comp->powerDissipation = 0.0;
                        break;
                }
            }

            // Update wire states
            foreach ($this->project->wires as &$wire) {
                $wireCurrent = $currents[$wire->id] ?? 0.0;
                $wire->current = abs($wireCurrent);

                // Calculate voltage drop across wire
                if (isset($voltages[$wire->startPortId]) && isset($voltages[$wire->endPortId])) {
                    $wire->voltageDrop = abs($voltages[$wire->startPortId] - $voltages[$wire->endPortId]);
                }
            }

            // Update component thermal states
            foreach ($this->project->components as &$comp) {
                $thermalProps = ThermalModel::getDefaultThermalProperties(
                    $comp->type,
                    $comp->properties->material ?? 'copper'
                );
                
                // Update temperature if thermal simulation enabled
                if ($settings['enableThermal']) {
                    $comp->temperature = ThermalModel::updateTemperature(
                        $comp->temperature,
                        $comp->powerDissipation,
                        $thermalProps,
                        $deltaTime
                    );
                }
                
                // Get thermal state
                $thermalState = ThermalModel::getThermalState(
                    $comp->temperature,
                    $comp->powerDissipation,
                    $thermalProps
                );
                
                // Update failure state if enabled
                if ($settings['enableFailures'] && $thermalState['isFailed']) {
                    $comp->isFailed = true;
                    $comp->failureType = 'thermal';
                }
                
                // Update warning level
                if ($thermalState['isOverheating']) {
                    $tempRatio = $comp->temperature / $thermalProps['maxTemperature'];
                    if ($tempRatio > 0.95) {
                        $comp->warningLevel = 'critical';
                    } elseif ($tempRatio > 0.90) {
                        $comp->warningLevel = 'high';
                    } elseif ($tempRatio > 0.80) {
                        $comp->warningLevel = 'medium';
                    } else {
                        $comp->warningLevel = 'low';
                    }
                } else {
                    $comp->warningLevel = 'none';
                }
            }
            
            $this->simulation->time += $deltaTime;
        }
    }
    
    /**
     * Reset simulation to initial state
     */
    public function resetSimulation(): void {
        $ambientTemp = $this->project->settings['ambientTemperature'];
        
        foreach ($this->project->components as &$comp) {
            $comp->temperature = $ambientTemp;
            $comp->currentFlow = 0.0;
            $comp->voltageDrop = 0.0;
            $comp->powerDissipation = 0.0;
            $comp->isFailed = false;
            $comp->failureType = 'none';
            $comp->warningLevel = 'none';
        }
        
        $this->simulation = new SimulationState();
    }
    
    // ==================== Validation ====================
    
    /**
     * Validate circuit completeness and correctness
     */
    public function validateCircuit(): array {
        $errors = [];
        $warnings = [];
        
        // Check for power source
        $hasPowerSource = false;
        foreach ($this->project->components as $comp) {
            if (in_array($comp->type, ['dcSource', 'acSource', 'pulseGenerator'])) {
                $hasPowerSource = true;
                break;
            }
        }
        if (!$hasPowerSource) {
            $errors[] = 'Circuit requires a power source';
        }
        
        // Check for ground
        $hasGround = false;
        foreach ($this->project->components as $comp) {
            if ($comp->type === 'ground') {
                $hasGround = true;
                break;
            }
        }
        if (!$hasGround) {
            $errors[] = 'Circuit requires a ground connection';
        }
        
        // Check for unconnected components
        $connectedPorts = [];
        foreach ($this->project->wires as $wire) {
            $connectedPorts[$wire->startPortId] = true;
            $connectedPorts[$wire->endPortId] = true;
        }
        
        foreach ($this->project->components as $comp) {
            $unconnectedCount = 0;
            foreach ($comp->ports as $port) {
                if (!isset($connectedPorts[$port->id])) {
                    $unconnectedCount++;
                }
            }
            
            if ($unconnectedCount === count($comp->ports) && $comp->type !== 'ground') {
                $warnings[] = "{$comp->name} is not connected to the circuit";
            }
        }
        
        // Validate individual components
        foreach ($this->project->components as $comp) {
            $compErrors = ComponentValidator::validate($comp);
            $errors = array_merge($errors, $compErrors);
        }
        
        return [
            'isValid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }
    
    // ==================== Project Management ====================
    
    /**
     * Create new empty project
     */
    public function newProject(string $name = 'New Circuit'): void {
        $this->project = ComponentFactory::createProject($name);
        $this->selectedComponentId = null;
        $this->selectedWireId = null;
        $this->simulation = new SimulationState();
    }
    
    /**
     * Update project settings
     */
    public function updateSettings(array $settings): void {
        $this->project->settings = array_merge(
            $this->project->settings,
            $settings
        );
    }
    
    /**
     * Set view mode
     */
    public function setViewMode(string $mode): bool {
        if (in_array($mode, ['schematic', 'field3d', 'largescale'])) {
            $this->viewMode = $mode;
            return true;
        }
        return false;
    }
    
    // ==================== Serialization ====================
    
    /**
     * Export state to array for JSON serialization
     */
    public function toArray(): array {
        return [
            'project' => [
                'id' => $this->project->id,
                'name' => $this->project->name,
                'description' => $this->project->description,
                'createdAt' => $this->project->createdAt->format('c'),
                'updatedAt' => $this->project->updatedAt->format('c'),
                'components' => array_map(fn($c) => $c->toArray(), $this->project->components),
                'wires' => array_map(fn($w) => [
                    'id' => $w->id,
                    'startPortId' => $w->startPortId,
                    'endPortId' => $w->endPortId,
                    'startComponentId' => $w->startComponentId,
                    'endComponentId' => $w->endComponentId,
                    'points' => $w->points,
                    'material' => $w->material,
                    'crossSection' => $w->crossSection,
                    'current' => $w->current,
                    'temperature' => $w->temperature
                ], $this->project->wires),
                'settings' => $this->project->settings
            ],
            'simulation' => [
                'isRunning' => $this->simulation->isRunning,
                'time' => $this->simulation->time,
                'isCircuitComplete' => $this->simulation->isCircuitComplete,
                'errors' => $this->simulation->errors,
                'warnings' => $this->simulation->warnings
            ],
            'selectedComponentId' => $this->selectedComponentId,
            'selectedWireId' => $this->selectedWireId,
            'viewMode' => $this->viewMode
        ];
    }
    
    /**
     * Save state to JSON file
     */
    public function saveToFile(string $filepath): bool {
        $json = json_encode($this->toArray(), JSON_PRETTY_PRINT);
        return file_put_contents($filepath, $json) !== false;
    }
    
    /**
     * Load state from JSON file
     */
    public static function loadFromFile(string $filepath): ?self {
        if (!file_exists($filepath)) return null;
        
        $json = file_get_contents($filepath);
        $data = json_decode($json, true);
        
        if (!$data) return null;
        
        // Reconstruct project (simplified - full implementation would rebuild all objects)
        $state = new self();
        // ... reconstruction logic here
        
        return $state;
    }
    
    /**
     * Export to session for persistence between requests
     */
    public function saveToSession(): void {
        $_SESSION['circuit_state'] = $this->toArray();
    }
    
    /**
     * Load from session
     */
    public static function loadFromSession(): ?self {
        if (!isset($_SESSION['circuit_state'])) return null;
        
        $state = new self();
        // ... reconstruction logic here
        
        return $state;
    }
}
