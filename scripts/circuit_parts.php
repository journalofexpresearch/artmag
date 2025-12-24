
<?php
require_once __DIR__ . '/math_main.php';

/**
 * Circuit Component Types and Definitions
 * 
 * Defines all available component types with their properties,
 * visual representations, and factory methods.
 */

class ComponentPort {
    public function __construct(
        public string $id,
        public array $position,  // ['x' => float, 'y' => float]
        public string $type,     // 'input', 'output', 'bidirectional'
        public ?string $connectedTo = null
    ) {}
}

class ComponentProperties {
    // Electrical
    public ?float $resistance = null;
    public ?float $capacitance = null;
    public ?float $inductance = null;
    public ?float $voltage = null;
    public ?float $current = null;
    public ?float $frequency = null;
    
    // Magnetic
    public ?int $turns = null;
    public ?float $radius = null;
    public ?float $innerRadius = null;
    public ?float $outerRadius = null;
    public ?float $length = null;
    public ?float $separation = null;
    
    // Physical
    public ?string $material = null;  // 'copper', 'aluminum', 'iron'
    public ?float $crossSection = null;
    
    // State
    public ?bool $isOpen = null;
    public ?float $pulseWidth = null;
    public ?float $dutyCycle = null;
    
    public function __construct(array $props = []) {
        foreach ($props as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }
}

class CircuitComponent {
    public function __construct(
        public string $id,
        public string $type,
        public string $category,
        public string $name,
        public array $position,      // ['x' => float, 'y' => float]
        public float $rotation,
        public array $ports,         // Array of ComponentPort
        public ComponentProperties $properties,
        public bool $isSelected = false,
        public bool $isHovered = false,
        
        // Runtime state
        public float $temperature = 25.0,
        public float $currentFlow = 0.0,
        public float $voltageDrop = 0.0,
        public float $powerDissipation = 0.0,
        
        // Failure state
        public bool $isFailed = false,
        public string $failureType = 'none',  // 'none', 'thermal', 'electrical', 'magnetic'
        public string $warningLevel = 'none'  // 'none', 'low', 'medium', 'high', 'critical'
    ) {}
    
    public function toArray(): array {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'category' => $this->category,
            'name' => $this->name,
            'position' => $this->position,
            'rotation' => $this->rotation,
            'ports' => array_map(fn($p) => [
                'id' => $p->id,
                'position' => $p->position,
                'type' => $p->type,
                'connectedTo' => $p->connectedTo
            ], $this->ports),
            'properties' => get_object_vars($this->properties),
            'temperature' => $this->temperature,
            'currentFlow' => $this->currentFlow,
            'voltageDrop' => $this->voltageDrop,
            'powerDissipation' => $this->powerDissipation,
            'isFailed' => $this->isFailed,
            'failureType' => $this->failureType,
            'warningLevel' => $this->warningLevel
        ];
    }
}

class Wire {
    public function __construct(
        public string $id,
        public string $startPortId,
        public string $endPortId,
        public string $startComponentId,
        public string $endComponentId,
        public array $points,        // Array of ['x' => float, 'y' => float]
        public string $material = 'copper',
        public float $crossSection = 1e-6,
        public float $current = 0.0,
        public float $temperature = 25.0,
        public bool $isSelected = false
    ) {}
}

class CircuitProject {
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public DateTime $createdAt,
        public DateTime $updatedAt,
        public array $components,    // Array of CircuitComponent
        public array $wires,         // Array of Wire
        public array $settings       // SimulationSettings as array
    ) {}
}

/**
 * Component Definitions Registry
 */
class ComponentDefinitions {
    
    const DEFINITIONS = [
        'resistor' => [
            'category' => 'passive',
            'label' => 'Resistor',
            'icon' => '⏛',
            'defaultProps' => ['resistance' => 1000],
            'portCount' => 2,
            'width' => 80,
            'height' => 30,
            'color' => 'hsl(25, 80%, 55%)'
        ],
        'capacitor' => [
            'category' => 'passive',
            'label' => 'Capacitor',
            'icon' => '⊣⊢',
            'defaultProps' => ['capacitance' => 1e-6],
            'portCount' => 2,
            'width' => 60,
            'height' => 40,
            'color' => 'hsl(200, 70%, 50%)'
        ],
        'inductor' => [
            'category' => 'passive',
            'label' => 'Inductor',
            'icon' => '⌇',
            'defaultProps' => ['inductance' => 1e-3],
            'portCount' => 2,
            'width' => 80,
            'height' => 30,
            'color' => 'hsl(280, 60%, 55%)'
        ],
        'dcSource' => [
            'category' => 'source',
            'label' => 'DC Source',
            'icon' => '⊕',
            'defaultProps' => ['voltage' => 12],
            'portCount' => 2,
            'width' => 50,
            'height' => 50,
            'color' => 'hsl(120, 70%, 45%)'
        ],
        'acSource' => [
            'category' => 'source',
            'label' => 'AC Source',
            'icon' => '∿',
            'defaultProps' => ['voltage' => 120, 'frequency' => 60],
            'portCount' => 2,
            'width' => 50,
            'height' => 50,
            'color' => 'hsl(120, 70%, 45%)'
        ],
        'ground' => [
            'category' => 'passive',
            'label' => 'Ground',
            'icon' => '⏚',
            'defaultProps' => [],
            'portCount' => 1,
            'width' => 40,
            'height' => 40,
            'color' => 'hsl(0, 0%, 50%)'
        ],
        'wire' => [
            'category' => 'passive',
            'label' => 'Wire',
            'icon' => '─',
            'defaultProps' => ['material' => 'copper', 'crossSection' => 1e-6],
            'portCount' => 2,
            'width' => 40,
            'height' => 10,
            'color' => 'hsl(25, 80%, 55%)'
        ],
        'coil' => [
            'category' => 'magnetic',
            'label' => 'Coil',
            'icon' => '◎',
            'defaultProps' => ['turns' => 100, 'radius' => 0.01, 'current' => 1],
            'portCount' => 2,
            'width' => 60,
            'height' => 60,
            'color' => 'hsl(280, 60%, 55%)'
        ],
        'solenoid' => [
            'category' => 'magnetic',
            'label' => 'Solenoid',
            'icon' => '⌬',
            'defaultProps' => ['turns' => 500, 'length' => 0.1, 'radius' => 0.02, 'current' => 1],
            'portCount' => 2,
            'width' => 100,
            'height' => 40,
            'color' => 'hsl(280, 60%, 55%)'
        ],
        'toroid' => [
            'category' => 'magnetic',
            'label' => 'Toroid',
            'icon' => '◯',
            'defaultProps' => ['turns' => 200, 'innerRadius' => 0.03, 'outerRadius' => 0.05, 'current' => 1],
            'portCount' => 2,
            'width' => 70,
            'height' => 70,
            'color' => 'hsl(280, 60%, 55%)'
        ],
        'helmholtz' => [
            'category' => 'magnetic',
            'label' => 'Helmholtz Coils',
            'icon' => '◎◎',
            'defaultProps' => ['turns' => 100, 'radius' => 0.1, 'separation' => 0.1, 'current' => 1],
            'portCount' => 2,
            'width' => 100,
            'height' => 60,
            'color' => 'hsl(280, 60%, 55%)'
        ],
        'transformer' => [
            'category' => 'magnetic',
            'label' => 'Transformer',
            'icon' => '⧫',
            'defaultProps' => ['turns' => 100],
            'portCount' => 4,
            'width' => 80,
            'height' => 60,
            'color' => 'hsl(280, 60%, 55%)'
        ],
        'switch' => [
            'category' => 'switch',
            'label' => 'Switch',
            'icon' => '⊗',
            'defaultProps' => ['isOpen' => true],
            'portCount' => 2,
            'width' => 60,
            'height' => 30,
            'color' => 'hsl(0, 0%, 50%)'
        ],
        'relay' => [
            'category' => 'switch',
            'label' => 'Relay',
            'icon' => '⌻',
            'defaultProps' => ['isOpen' => true, 'voltage' => 5],
            'portCount' => 4,
            'width' => 70,
            'height' => 50,
            'color' => 'hsl(0, 0%, 50%)'
        ],
        'transistor' => [
            'category' => 'active',
            'label' => 'Transistor',
            'icon' => '⊿',
            'defaultProps' => [],
            'portCount' => 3,
            'width' => 50,
            'height' => 50,
            'color' => 'hsl(45, 70%, 50%)'
        ],
        'pulseGenerator' => [
            'category' => 'source',
            'label' => 'Pulse Generator',
            'icon' => '⊞',
            'defaultProps' => ['voltage' => 5, 'frequency' => 1000, 'pulseWidth' => 0.5, 'dutyCycle' => 0.5],
            'portCount' => 2,
            'width' => 70,
            'height' => 50,
            'color' => 'hsl(120, 70%, 45%)'
        ],
        'hallSensor' => [
            'category' => 'sensor',
            'label' => 'Hall Sensor',
            'icon' => '⌾',
            'defaultProps' => [],
            'portCount' => 3,
            'width' => 40,
            'height' => 40,
            'color' => 'hsl(200, 80%, 50%)'
        ],
        'currentSensor' => [
            'category' => 'sensor',
            'label' => 'Current Sensor',
            'icon' => 'A',
            'defaultProps' => [],
            'portCount' => 3,
            'width' => 50,
            'height' => 40,
            'color' => 'hsl(200, 80%, 50%)'
        ],
        'tempSensor' => [
            'category' => 'sensor',
            'label' => 'Temp Sensor',
            'icon' => '🌡',
            'defaultProps' => [],
            'portCount' => 2,
            'width' => 40,
            'height' => 40,
            'color' => 'hsl(30, 90%, 55%)'
        ],
        'junction' => [
            'category' => 'passive',
            'label' => 'Junction',
            'icon' => '•',
            'defaultProps' => [],
            'portCount' => 4,
            'width' => 20,
            'height' => 20,
            'color' => 'hsl(220, 80%, 60%)'
        ]
    ];
    
    /**
     * Get definition for a component type
     */
    public static function get(string $type): ?array {
        return self::DEFINITIONS[$type] ?? null;
    }
    
    /**
     * Get all component types by category
     */
    public static function getByCategory(string $category): array {
        $result = [];
        foreach (self::DEFINITIONS as $type => $def) {
            if ($def['category'] === $category) {
                $result[$type] = $def;
            }
        }
        return $result;
    }
    
    /**
     * Get all categories
     */
    public static function getCategories(): array {
        return [
            'source' => 'Power Sources',
            'passive' => 'Passive Components',
            'active' => 'Active Components',
            'magnetic' => 'Magnetic Components',
            'sensor' => 'Sensors',
            'switch' => 'Switches'
        ];
    }
    
    /**
     * Get port positions for a component type
     */
    public static function getPortPositions(string $type, float $width, float $height): array {
        $def = self::get($type);
        if (!$def) return [];
        
        $portCount = $def['portCount'];
        
        switch ($portCount) {
            case 1:
                return [['x' => $width / 2, 'y' => $height]];
            
            case 2:
                return [
                    ['x' => 0, 'y' => $height / 2],
                    ['x' => $width, 'y' => $height / 2]
                ];
            
            case 3:
                return [
                    ['x' => 0, 'y' => $height / 2],
                    ['x' => $width, 'y' => $height / 2],
                    ['x' => $width / 2, 'y' => $height]
                ];
            
            case 4:
                return [
                    ['x' => 0, 'y' => $height / 3],
                    ['x' => 0, 'y' => (2 * $height) / 3],
                    ['x' => $width, 'y' => $height / 3],
                    ['x' => $width, 'y' => (2 * $height) / 3]
                ];
            
            default:
                return [['x' => $width / 2, 'y' => $height / 2]];
        }
    }
}

/**
 * Component Factory
 */
class ComponentFactory {
    
    /**
     * Create a new component with default values
     */
    public static function create(
        string $type,
        array $position,  // ['x' => float, 'y' => float]
        ?string $id = null
    ): ?CircuitComponent {
        $def = ComponentDefinitions::get($type);
        if (!$def) return null;
        
        $componentId = $id ?? uniqid("{$type}_");
        
        // Create ports
        $ports = [];
        $portPositions = ComponentDefinitions::getPortPositions(
            $type, 
            $def['width'], 
            $def['height']
        );
        
        for ($i = 0; $i < $def['portCount']; $i++) {
            $ports[] = new ComponentPort(
                id: "{$componentId}_port_{$i}",
                position: $portPositions[$i],
                type: 'bidirectional',
                connectedTo: null
            );
        }
        
        return new CircuitComponent(
            id: $componentId,
            type: $type,
            category: $def['category'],
            name: $def['label'],
            position: $position,
            rotation: 0.0,
            ports: $ports,
            properties: new ComponentProperties($def['defaultProps'])
        );
    }
    
    /**
     * Create a wire connection
     */
    public static function createWire(
        string $startPortId,
        string $endPortId,
        string $startComponentId,
        string $endComponentId,
        array $points = [],
        string $material = 'copper'
    ): Wire {
        return new Wire(
            id: uniqid('wire_'),
            startPortId: $startPortId,
            endPortId: $endPortId,
            startComponentId: $startComponentId,
            endComponentId: $endComponentId,
            points: $points,
            material: $material
        );
    }
    
    /**
     * Create a new circuit project
     */
    public static function createProject(
        string $name,
        string $description = ''
    ): CircuitProject {
        return new CircuitProject(
            id: uniqid('project_'),
            name: $name,
            description: $description,
            createdAt: new DateTime(),
            updatedAt: new DateTime(),
            components: [],
            wires: [],
            settings: [
                'timeStep' => 1e-6,
                'frequency' => 60,
                'ambientTemperature' => 25.0,
                'bufferScale' => 1.0,
                'enableThermal' => true,
                'enableFailures' => true,
                'fieldResolution' => 50
            ]
        );
    }
}

/**
 * Component Validation
 */
class ComponentValidator {
    
    /**
     * Validate component properties
     */
    public static function validate(CircuitComponent $component): array {
        $errors = [];
        $props = $component->properties;
        
        // Validate based on type
        switch ($component->type) {
            case 'resistor':
                if ($props->resistance !== null && $props->resistance <= 0) {
                    $errors[] = "Resistance must be positive";
                }
                break;
            
            case 'capacitor':
                if ($props->capacitance !== null && $props->capacitance <= 0) {
                    $errors[] = "Capacitance must be positive";
                }
                break;
            
            case 'inductor':
                if ($props->inductance !== null && $props->inductance <= 0) {
                    $errors[] = "Inductance must be positive";
                }
                break;
            
            case 'dcSource':
            case 'acSource':
                if ($props->voltage !== null && abs($props->voltage) > SIMULATION_LIMITS['maxVoltage']) {
                    $errors[] = "Voltage exceeds safe limits";
                }
                break;
            
            case 'coil':
            case 'solenoid':
            case 'helmholtz':
                if ($props->turns !== null && $props->turns <= 0) {
                    $errors[] = "Number of turns must be positive";
                }
                if ($props->radius !== null && $props->radius <= 0) {
                    $errors[] = "Radius must be positive";
                }
                break;
        }
        
        // Check temperature
        if ($component->temperature > SIMULATION_LIMITS['maxTemperature']) {
            $errors[] = "Component overheating";
        }
        
        return $errors;
    }
    
    /**
     * Check if component should fail
     */
    public static function checkFailure(CircuitComponent $component): array {
        $def = ComponentDefinitions::get($component->type);
        if (!$def) return ['failed' => false, 'type' => 'none'];
        
        // Thermal failure
        $material = $component->properties->material ?? 'copper';
        if (isset(MATERIAL_PROPERTIES[$material])) {
            $maxTemp = MATERIAL_PROPERTIES[$material]['maxTemp'];
            if ($component->temperature > $maxTemp) {
                return ['failed' => true, 'type' => 'thermal'];
            }
        }
        
        // Electrical failure
        if ($component->currentFlow > SIMULATION_LIMITS['maxCurrent']) {
            return ['failed' => true, 'type' => 'electrical'];
        }
        
        // Magnetic saturation (for magnetic components)
        if ($component->category === 'magnetic') {
            // Could implement magnetic saturation checks here
        }
        
        return ['failed' => false, 'type' => 'none'];
    }
}
