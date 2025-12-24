<?php
/**
 * Mathematical and Physical Constants
 * Includes Kunferman Buffer constants and standard physics values
 */

// Kunferman Buffer Constants
define('REPHI', 1.618033988749895);              // Golden Ratio φ
define('ALPHA_PI', 1.5642654391586674);          // Kunferman Constant
define('KUNFERMAN_DELTA', 0.053768549591227);    // φ - AlphaPi

// Physical Constants
define('MU_0', 1.25663706212e-6);                // Vacuum permeability (H/m)
define('EPSILON_0', 8.854187817e-12);            // Vacuum permittivity (F/m)
define('SPEED_OF_LIGHT', 299792458);             // m/s
define('ELECTRON_CHARGE', 1.602176634e-19);      // Coulombs
define('BOLTZMANN', 1.380649e-23);               // J/K

// Material Properties
const MATERIAL_PROPERTIES = [
    'copper' => [
        'resistivity' => 1.68e-8,           // Ω·m at 20°C
        'permeability' => 0.999994,         // Relative
        'conductivity' => 5.96e7,           // S/m
        'thermalCoefficient' => 0.00393,    // per °C
        'maxTemp' => 200,                   // °C
        'color' => 'hsl(25, 80%, 55%)'
    ],
    'aluminum' => [
        'resistivity' => 2.65e-8,
        'permeability' => 1.000022,
        'conductivity' => 3.77e7,
        'thermalCoefficient' => 0.00429,
        'maxTemp' => 150,
        'color' => 'hsl(220, 10%, 75%)'
    ],
    'iron' => [
        'resistivity' => 9.71e-8,
        'permeability' => 5000,              // Highly variable
        'conductivity' => 1.03e7,
        'thermalCoefficient' => 0.00651,
        'maxTemp' => 300,
        'color' => 'hsl(0, 0%, 45%)'
    ],
    'air' => [
        'resistivity' => 1e16,
        'permeability' => 1.00000037,
        'conductivity' => 0,
        'thermalCoefficient' => 0,
        'maxTemp' => PHP_FLOAT_MAX,
        'color' => 'transparent'
    ]
];

// Component Defaults
const COMPONENT_DEFAULTS = [
    'resistor' => ['resistance' => 1000],                    // 1kΩ
    'capacitor' => ['capacitance' => 1e-6],                  // 1µF
    'inductor' => ['inductance' => 1e-3],                    // 1mH
    'dcSource' => ['voltage' => 12],                         // 12V
    'acSource' => ['voltage' => 120, 'frequency' => 60],     // 120V 60Hz
    'coil' => ['turns' => 100, 'radius' => 0.01, 'current' => 1],
    'solenoid' => ['turns' => 500, 'length' => 0.1, 'radius' => 0.02, 'current' => 1],
    'toroid' => ['turns' => 200, 'innerRadius' => 0.03, 'outerRadius' => 0.05, 'current' => 1],
    'helmholtz' => ['turns' => 100, 'radius' => 0.1, 'separation' => 0.1, 'current' => 1],
    'wire' => ['length' => 0.1, 'crossSection' => 1e-6, 'material' => 'copper']
];

// Simulation Limits
const SIMULATION_LIMITS = [
    'maxCurrent' => 1000,           // Amps
    'maxVoltage' => 10000,          // Volts
    'maxTemperature' => 500,        // °C
    'maxFieldStrength' => 100,      // Tesla
    'minResistance' => 1e-6,        // Ohms
    'gridResolution' => 50,         // Points per axis
    'timeStep' => 1e-6              // Seconds
];

// Earth's Magnetic Field
const EARTH_FIELD = [
    'minStrength' => 25e-6,         // Tesla (equator)
    'maxStrength' => 65e-6,         // Tesla (poles)
    'averageStrength' => 50e-6,     // Tesla
    'dipoleStrength' => 7.94e22     // A·m²
];
