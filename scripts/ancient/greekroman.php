<?php
require_once __DIR__ . '/circuit_parts.php';
require_once __DIR__ . '/magnetic_field.php';
require_once __DIR__ . '/math_main.php';

/**
 * Temple Geometry Electromagnetic Components
 * 
 * Ancient sacred architecture modeled as resonant cavity structures
 * and electromagnetic field processors. Scales sacred geometry ratios
 * to modern power systems and planetary-scale field generation.
 */

class TempleGeometry {
    
    /**
     * Calculate resonant frequency from chamber dimensions
     * f = c / (2 * L) where L is the longest dimension
     */
    public static function resonantFrequency(float $length, float $width, float $height): float {
        $L = max($length, $width, $height);
        return SPEED_OF_LIGHT / (2 * $L);
    }
    
    /**
     * Calculate column spacing for wavelength resonance
     * spacing = λ / 2 for optimal standing wave formation
     */
    public static function columnSpacing(float $frequency): float {
        $wavelength = SPEED_OF_LIGHT / $frequency;
        return $wavelength / 2;
    }
    
    /**
     * Sacred geometry ratio (Golden Ratio φ) for optimal field coupling
     */
    public static function sacredRatio(float $dimension): float {
        return $dimension * REPHI; // Use phi for harmonic proportions
    }
    
    /**
     * Calculate quality factor (Q) for resonant cavity
     * Higher Q = sharper resonance, better field containment
     */
    public static function qualityFactor(
        float $volume,
        float $surfaceArea,
        string $material = 'stone'
    ): float {
        // Stone temples have high Q due to low conductivity
        $materialQ = [
            'stone' => 10000,
            'limestone' => 12000,
            'granite' => 15000,
            'marble' => 11000
        ];
        
        $baseQ = $materialQ[$material] ?? 10000;
        
        // Larger volume/surface ratio = higher Q
        $geometryFactor = $volume / $surfaceArea;
        
        return $baseQ * (1 + $geometryFactor / 10);
    }
}

class MonopteralField {
    /**
     * Circular temple - creates toroidal magnetic field
     * Functions like a single-turn toroid with atmospheric coupling
     */
    public static function calculateField(
        float $radius,        // Temple radius (meters)
        int $columnCount,     // Number of columns (field nodes)
        float $current,       // Equivalent current flow
        float $lat, float $lon, float $alt,  // Field point
        float $bufferScale = 1.0
    ): Vector3 {
        // Columns act as discrete field sources
        // Create superposition of fields from each column
        
        $totalField = new Vector3(0, 0, 0);
        $angleStep = 2 * M_PI / $columnCount;
        
        // Temple center at reference coordinates
        $centerLat = 0;
        $centerLon = 0;
        $centerAlt = 0;
        
        for ($i = 0; $i < $columnCount; $i++) {
            $angle = $i * $angleStep;
            
            // Column position in local coordinates
            $colX = $radius * cos($angle);
            $colY = $radius * sin($angle);
            
            // Convert to geographic offset
            $colLat = $centerLat + ($colY / 111320); // meters to degrees
            $colLon = $centerLon + ($colX / (111320 * cos(deg2rad($centerLat))));
            
            // Calculate field contribution from this column
            $direction = new Vector3(
                -sin($angle), // Tangent to circle
                cos($angle),
                0
            );
            
            $fieldContribution = SphericalMagneticField::biotSavartSpherical(
                $colLat, $colLon, $centerAlt,
                $lat, $lon, $alt,
                $current / $columnCount, // Distribute current
                $direction,
                2 * M_PI * $radius / $columnCount, // Segment length
                $bufferScale
            );
            
            $totalField = $totalField->add($fieldContribution);
        }
        
        return $totalField;
    }
    
    /**
     * Get resonant frequencies for circular temple
     * Circular geometry supports multiple harmonic modes
     */
    public static function harmonicModes(float $radius, int $maxMode = 5): array {
        $modes = [];
        
        for ($n = 1; $n <= $maxMode; $n++) {
            // Circular resonance: f_n = (n * c) / (2π * r)
            $frequency = ($n * SPEED_OF_LIGHT) / (2 * M_PI * $radius);
            $modes[] = [
                'mode' => $n,
                'frequency' => $frequency,
                'wavelength' => SPEED_OF_LIGHT / $frequency
            ];
        }
        
        return $modes;
    }
}

class PeripteralField {
    /**
     * Rectangular temple with full colonnade
     * Creates rectangular resonant cavity with dual-mode coupling
     * Outer colonnade + inner chamber = transformer behavior
     */
    public static function calculateField(
        float $length,
        float $width,
        float $height,
        int $columnsLength,  // Columns along length
        int $columnsWidth,   // Columns along width
        float $outerCurrent, // Current in outer colonnade
        float $innerCurrent, // Current in inner chamber
        float $lat, float $lon, float $alt,
        float $bufferScale = 1.0
    ): Vector3 {
        // Outer peristyle creates primary field
        $outerField = self::colonnadeField(
            $length, $width, $columnsLength, $columnsWidth,
            $outerCurrent, $lat, $lon, $alt, $bufferScale
        );
        
        // Inner chamber creates secondary coupled field
        // Inner dimensions follow golden ratio
        $innerLength = $length / REPHI;
        $innerWidth = $width / REPHI;
        
        $innerField = self::chamberField(
            $innerLength, $innerWidth, $height,
            $innerCurrent, $lat, $lon, $alt, $bufferScale
        );
        
        // Transformer coupling - fields interact
        return $outerField->add($innerField);
    }
    
    private static function colonnadeField(
        float $length, float $width,
        int $columnsLength, int $columnsWidth,
        float $current,
        float $lat, float $lon, float $alt,
        float $bufferScale
    ): Vector3 {
        $totalField = new Vector3(0, 0, 0);
        
        // Distribute columns around perimeter
        $spacing = $length / $columnsLength;
        
        // Front and back colonnades
        for ($i = 0; $i < $columnsLength; $i++) {
            $x = $i * $spacing - $length / 2;
            
            // Front row
            $colLat = $lat + ($x / 111320);
            $colLon = $lon + ((-$width/2) / (111320 * cos(deg2rad($lat))));
            
            $direction = new Vector3(1, 0, 0); // Along length
            $field = SphericalMagneticField::biotSavartSpherical(
                $colLat, $colLon, $alt,
                $lat, $lon, $alt,
                $current / ($columnsLength * 2 + $columnsWidth * 2),
                $direction, $spacing, $bufferScale
            );
            $totalField = $totalField->add($field);
            
            // Back row
            $colLon = $lon + (($width/2) / (111320 * cos(deg2rad($lat))));
            $field = SphericalMagneticField::biotSavartSpherical(
                $colLat, $colLon, $alt,
                $lat, $lon, $alt,
                $current / ($columnsLength * 2 + $columnsWidth * 2),
                $direction, $spacing, $bufferScale
            );
            $totalField = $totalField->add($field);
        }
        
        // Left and right colonnades (similar logic)
        // ... (simplified for brevity)
        
        return $totalField;
    }
    
    private static function chamberField(
        float $length, float $width, float $height,
        float $current,
        float $lat, float $lon, float $alt,
        float $bufferScale
    ): Vector3 {
        // Inner chamber as solenoid-like structure
        $turns = 10; // Effective turns based on geometry
        $field = SphericalMagneticField::geoCircularLoop(
            $lat, $lon, $alt,
            sqrt($length * $width) / M_PI, // Equivalent radius
            $turns, $current,
            $lat, $lon, $alt,
            36, $bufferScale
        );
        
        return $field;
    }
    
    /**
     * Rectangular cavity modes (TE and TM modes)
     */
    public static function cavityModes(
        float $length, float $width, float $height
    ): array {
        $modes = [];
        
        // Calculate fundamental and first few harmonic modes
        for ($m = 0; $m <= 2; $m++) {
            for ($n = 0; $n <= 2; $n++) {
                for ($p = 0; $p <= 2; $p++) {
                    if ($m == 0 && $n == 0 && $p == 0) continue;
                    
                    // Resonant frequency for TE/TM modes
                    $f = (SPEED_OF_LIGHT / 2) * sqrt(
                        ($m / $length)**2 + 
                        ($n / $width)**2 + 
                        ($p / $height)**2
                    );
                    
                    $modes[] = [
                        'mode' => "TE_{$m}{$n}{$p}",
                        'frequency' => $f,
                        'wavelength' => SPEED_OF_LIGHT / $f
                    ];
                }
            }
        }
        
        return $modes;
    }
}

class HypaethralField {
    /**
     * Large open-air temple with dense colonnade
     * Multi-cavity waveguide with atmospheric coupling
     * Open roof couples to ionosphere - planetary-scale transmission
     */
    public static function calculateField(
        float $length,
        float $width,
        float $columnDensity, // Columns per square meter
        float $current,
        float $lat, float $lon, float $alt,
        bool $atmosphericCoupling = true,
        float $bufferScale = 1.0
    ): Vector3 {
        // Dense column grid creates phased array
        $totalColumns = (int)($length * $width * $columnDensity);
        
        // Base field from colonnade
        $baseField = self::phasedArrayField(
            $length, $width, $totalColumns,
            $current, $lat, $lon, $alt, $bufferScale
        );
        
        // Atmospheric coupling - open roof receives/transmits
        if ($atmosphericCoupling) {
            $ionosphereAlt = 100000; // 100km altitude
            $atmosphericField = self::ionosphericCoupling(
                $lat, $lon, $alt, $ionosphereAlt,
                $current, $bufferScale
            );
            
            $baseField = $baseField->add($atmosphericField);
        }
        
        return $baseField;
    }
    
    private static function phasedArrayField(
        float $length, float $width, int $columns,
        float $current,
        float $lat, float $lon, float $alt,
        float $bufferScale
    ): Vector3 {
        // Distribute columns in grid pattern
        $rows = (int)sqrt($columns);
        $cols = (int)($columns / $rows);
        
        $rowSpacing = $length / $rows;
        $colSpacing = $width / $cols;
        
        $totalField = new Vector3(0, 0, 0);
        
        for ($i = 0; $i < $rows; $i++) {
            for ($j = 0; $j < $cols; $j++) {
                $x = $i * $rowSpacing - $length / 2;
                $y = $j * $colSpacing - $width / 2;
                
                $colLat = $lat + ($x / 111320);
                $colLon = $lon + ($y / (111320 * cos(deg2rad($lat))));
                
                // Each column contributes to array
                $direction = new Vector3(0, 0, 1); // Vertical
                $field = SphericalMagneticField::biotSavartSpherical(
                    $colLat, $colLon, $alt,
                    $lat, $lon, $alt,
                    $current / $columns,
                    $direction,
                    $rowSpacing,
                    $bufferScale
                );
                
                $totalField = $totalField->add($field);
            }
        }
        
        return $totalField;
    }
    
    private static function ionosphericCoupling(
        float $lat, float $lon, float $alt, float $ionosphereAlt,
        float $current, float $bufferScale
    ): Vector3 {
        // ELF/VLF propagation through Earth-ionosphere waveguide
        $distance = $ionosphereAlt - $alt;
        
        // Vertical current column from temple to ionosphere
        $direction = new Vector3(0, 0, 1);
        
        return SphericalMagneticField::biotSavartSpherical(
            $lat, $lon, $alt,
            $lat, $lon, $ionosphereAlt,
            $current,
            $direction,
            $distance,
            $bufferScale
        );
    }
    
    /**
     * ELF/VLF propagation modes in Earth-ionosphere waveguide
     */
    public static function waveguideModes(): array {
        // Schumann resonances - Earth-ionosphere cavity modes
        return [
            ['mode' => 1, 'frequency' => 7.83, 'name' => 'Fundamental Schumann'],
            ['mode' => 2, 'frequency' => 14.3, 'name' => 'Second harmonic'],
            ['mode' => 3, 'frequency' => 20.8, 'name' => 'Third harmonic'],
            ['mode' => 4, 'frequency' => 27.3, 'name' => 'Fourth harmonic'],
            ['mode' => 5, 'frequency' => 33.8, 'name' => 'Fifth harmonic']
        ];
    }
}

/**
 * Add temple components to component definitions
 */
ComponentDefinitions::DEFINITIONS['monopteral'] = [
    'category' => 'temple',
    'label' => 'Monopteral Temple',
    'icon' => '◯',
    'defaultProps' => [
        'radius' => 10,           // meters
        'columnCount' => 12,
        'current' => 100,         // amperes
        'material' => 'marble'
    ],
    'portCount' => 2,
    'width' => 80,
    'height' => 80,
    'color' => 'hsl(280, 70%, 60%)'
];

ComponentDefinitions::DEFINITIONS['peripteral'] = [
    'category' => 'temple',
    'label' => 'Peripteral Temple',
    'icon' => '▭',
    'defaultProps' => [
        'length' => 30,           // meters
        'width' => 15,
        'height' => 12,
        'columnsLength' => 8,
        'columnsWidth' => 6,
        'outerCurrent' => 200,
        'innerCurrent' => 100,
        'material' => 'limestone'
    ],
    'portCount' => 4,             // Dual input/output
    'width' => 100,
    'height' => 60,
    'color' => 'hsl(280, 70%, 60%)'
];

ComponentDefinitions::DEFINITIONS['hypaethral'] = [
    'category' => 'temple',
    'label' => 'Hypaethral Temple',
    'icon' => '⊞',
    'defaultProps' => [
        'length' => 100,          // Large scale
        'width' => 50,
        'columnDensity' => 0.5,   // Columns per m²
        'current' => 1000,
        'atmosphericCoupling' => true,
        'material' => 'granite'
    ],
    'portCount' => 2,
    'width' => 120,
    'height' => 80,
    'color' => 'hsl(280, 70%, 60%)'
];
