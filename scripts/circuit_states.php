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
        
        // Create branches from wires
        foreach ($this->project->wires as $wire) {
            $branches[] = new CircuitBranch(
                id: $wire->id,
                startNode: $wire->startPortId,
                endNode: $wire->endPortId,
                current: 0.0,
                impedance: Impedance::resistor(0.01), // Small wire resistance
                type: 'wire',
                value: 0.01
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
