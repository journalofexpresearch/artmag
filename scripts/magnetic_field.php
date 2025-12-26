<?php
require_once __DIR__ . '/math_main.php';
require_once __DIR__ . '/sanity_buffer.php';

/**
 * Geodesic Magnetic Field Calculations
 * 
 * Implements proper spherical geometry for planetary-scale
 * electromagnetic field modeling with Kunferman Buffer regularization.
 * 
 * Suitable for MGAIA 7-point artificial magnetosphere calculations.
 */

class Vector3 {
    public function __construct(
        public float $x = 0.0,
        public float $y = 0.0,
        public float $z = 0.0
    ) {}
    
    public function magnitude(): float {
        return sqrt($this->x**2 + $this->y**2 + $this->z**2);
    }
    
    public function normalize(): Vector3 {
        $mag = $this->magnitude();
        if (abs($mag) < 1e-15) return new Vector3(0, 0, 1);
        return new Vector3($this->x/$mag, $this->y/$mag, $this->z/$mag);
    }
    
    public function scale(float $s): Vector3 {
        return new Vector3($this->x * $s, $this->y * $s, $this->z * $s);
    }
    
    public function add(Vector3 $v): Vector3 {
        return new Vector3($this->x + $v->x, $this->y + $v->y, $this->z + $v->z);
    }
    
    public function subtract(Vector3 $v): Vector3 {
        return new Vector3($this->x - $v->x, $this->y - $v->y, $this->z - $v->z);
    }
    
    public function cross(Vector3 $v): Vector3 {
        return new Vector3(
            $this->y * $v->z - $this->z * $v->y,
            $this->z * $v->x - $this->x * $v->z,
            $this->x * $v->y - $this->y * $v->x
        );
    }
    
    public function dot(Vector3 $v): float {
        return $this->x * $v->x + $this->y * $v->y + $this->z * $v->z;
    }
}

class GeodesicCalculations {
    // WGS-84 Earth ellipsoid parameters
    const EARTH_RADIUS_EQUATORIAL = 6378137.0;      // meters (semi-major axis)
    const EARTH_RADIUS_POLAR = 6356752.314245;      // meters (semi-minor axis)
    const EARTH_FLATTENING = 1/298.257223563;       // f = (a-b)/a
    const EARTH_ECCENTRICITY_SQ = 0.00669437999014; // e² = (a²-b²)/a²
    
    /**
     * Haversine formula - great circle distance on sphere
     * Accurate to ~0.5% for most Earth distances
     * Fast for real-time calculations
     */
    public static function haversineDistance(
        float $lat1, float $lon1, 
        float $lat2, float $lon2
    ): float {
        $R = self::EARTH_RADIUS_EQUATORIAL;
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat/2)**2 + 
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
             sin($dLon/2)**2;
        
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $R * $c;
    }
    
    /**
     * Vincenty formula - ellipsoidal Earth distance
     * Accurate to ~0.5mm, essential for precise MGAIA positioning
     * Uses iterative method
     */
    public static function vincentyDistance(
        float $lat1, float $lon1,
        float $lat2, float $lon2,
        int $maxIterations = 200
    ): float {
        $a = self::EARTH_RADIUS_EQUATORIAL;
        $b = self::EARTH_RADIUS_POLAR;
        $f = self::EARTH_FLATTENING;
        
        $L = deg2rad($lon2 - $lon1);
        $U1 = atan((1-$f) * tan(deg2rad($lat1)));
        $U2 = atan((1-$f) * tan(deg2rad($lat2)));
        
        $sinU1 = sin($U1); $cosU1 = cos($U1);
        $sinU2 = sin($U2); $cosU2 = cos($U2);
        
        $lambda = $L;
        $iterLimit = $maxIterations;
        
        do {
            $sinLambda = sin($lambda);
            $cosLambda = cos($lambda);
            
            $sinSigma = sqrt(
                ($cosU2 * $sinLambda)**2 + 
                ($cosU1 * $sinU2 - $sinU1 * $cosU2 * $cosLambda)**2
            );
            
            if (abs($sinSigma) < 1e-15) return 0.0; // Coincident points
            
            $cosSigma = $sinU1 * $sinU2 + $cosU1 * $cosU2 * $cosLambda;
            $sigma = atan2($sinSigma, $cosSigma);
            
            $sinAlpha = $cosU1 * $cosU2 * $sinLambda / $sinSigma;
            $cosSqAlpha = 1 - $sinAlpha**2;
            
            $cos2SigmaM = $cosSqAlpha != 0 ? 
                ($cosSigma - 2 * $sinU1 * $sinU2 / $cosSqAlpha) : 0;
            
            $C = $f/16 * $cosSqAlpha * (4 + $f * (4 - 3 * $cosSqAlpha));
            
            $lambdaP = $lambda;
            $lambda = $L + (1-$C) * $f * $sinAlpha * (
                $sigma + $C * $sinSigma * (
                    $cos2SigmaM + $C * $cosSigma * 
                    (-1 + 2 * $cos2SigmaM**2)
                )
            );
            
        } while (abs($lambda - $lambdaP) > 1e-12 && --$iterLimit > 0);
        
        if ($iterLimit == 0) {
            // Failed to converge, fall back to haversine
            return self::haversineDistance($lat1, $lon1, $lat2, $lon2);
        }
        
        $uSq = $cosSqAlpha * ($a**2 - $b**2) / ($b**2);
        $A = 1 + $uSq/16384 * (4096 + $uSq * (-768 + $uSq * (320 - 175 * $uSq)));
        $B = $uSq/1024 * (256 + $uSq * (-128 + $uSq * (74 - 47 * $uSq)));
        
        $deltaSigma = $B * $sinSigma * (
            $cos2SigmaM + $B/4 * (
                $cosSigma * (-1 + 2 * $cos2SigmaM**2) - 
                $B/6 * $cos2SigmaM * (-3 + 4 * $sinSigma**2) * 
                (-3 + 4 * $cos2SigmaM**2)
            )
        );
        
        return $b * $A * ($sigma - $deltaSigma);
    }
    
    /**
     * Geographic (lat/lon/alt) to ECEF (Earth-Centered Earth-Fixed) Cartesian
     * Essential for 3D field vector calculations
     */
    public static function geo2ecef(float $lat, float $lon, float $alt): Vector3 {
        $a = self::EARTH_RADIUS_EQUATORIAL;
        $e2 = self::EARTH_ECCENTRICITY_SQ;
        
        $latRad = deg2rad($lat);
        $lonRad = deg2rad($lon);
        
        $sinLat = sin($latRad);
        $cosLat = cos($latRad);
        $sinLon = sin($lonRad);
        $cosLon = cos($lonRad);
        
        // Radius of curvature in prime vertical
        $N = $a / sqrt(1 - $e2 * $sinLat**2);
        
        $x = ($N + $alt) * $cosLat * $cosLon;
        $y = ($N + $alt) * $cosLat * $sinLon;
        $z = ($N * (1 - $e2) + $alt) * $sinLat;
        
        return new Vector3($x, $y, $z);
    }
    
    /**
     * ECEF to Geographic (lat/lon/alt)
     * Uses iterative method for accuracy
     */
    public static function ecef2geo(Vector3 $ecef): array {
        $a = self::EARTH_RADIUS_EQUATORIAL;
        $b = self::EARTH_RADIUS_POLAR;
        $e2 = self::EARTH_ECCENTRICITY_SQ;
        
        $x = $ecef->x;
        $y = $ecef->y;
        $z = $ecef->z;
        
        $lon = atan2($y, $x);
        
        $p = sqrt($x**2 + $y**2);
        $lat = atan2($z, $p * (1 - $e2));
        
        // Iterate for accurate latitude
        for ($i = 0; $i < 10; $i++) {
            $sinLat = sin($lat);
            $N = $a / sqrt(1 - $e2 * $sinLat**2);
            $lat = atan2($z + $e2 * $N * $sinLat, $p);
        }
        
        $sinLat = sin($lat);
        $N = $a / sqrt(1 - $e2 * $sinLat**2);
        $alt = $p / cos($lat) - $N;
        
        return [
            'lat' => rad2deg($lat),
            'lon' => rad2deg($lon),
            'alt' => $alt
        ];
    }
    
    /**
     * ECEF to ENU (East-North-Up) local tangent plane
     * For local field vector calculations relative to observer
     */
    public static function ecef2enu(
        Vector3 $ecef, 
        float $lat0, float $lon0, float $alt0
    ): Vector3 {
        $origin = self::geo2ecef($lat0, $lon0, $alt0);
        $diff = $ecef->subtract($origin);
        
        $latRad = deg2rad($lat0);
        $lonRad = deg2rad($lon0);
        
        $sinLat = sin($latRad);
        $cosLat = cos($latRad);
        $sinLon = sin($lonRad);
        $cosLon = cos($lonRad);
        
        // Rotation matrix ECEF -> ENU
        $e = -$sinLon * $diff->x + $cosLon * $diff->y;
        $n = -$sinLat * $cosLon * $diff->x - $sinLat * $sinLon * $diff->y + $cosLat * $diff->z;
        $u = $cosLat * $cosLon * $diff->x + $cosLat * $sinLon * $diff->y + $sinLat * $diff->z;
        
        return new Vector3($e, $n, $u);
    }
}

class SphericalMagneticField {
    
    /**
     * Biot-Savart in spherical coordinates with geodesic distance
     * For planetary-scale current loop calculations
     */
    public static function biotSavartSpherical(
        float $lat1, float $lon1, float $alt1,  // Source position
        float $lat2, float $lon2, float $alt2,  // Field point
        float $current,
        Vector3 $currentDirection,
        float $segmentLength,
        float $bufferScale = 1.0
    ): Vector3 {
        // Convert to ECEF for vector operations
        $source = GeodesicCalculations::geo2ecef($lat1, $lon1, $alt1);
        $point = GeodesicCalculations::geo2ecef($lat2, $lon2, $alt2);
        
        // Vector from source to point
        $r = $point->subtract($source);
        $rMag = $r->magnitude();
        
        // Apply Kunferman regularization
        $rReg = SanityBuffer::regularizeDistance($rMag, $bufferScale);
        
        // Current element vector (I * dl)
        $Idl = $currentDirection->scale($current * $segmentLength);
        
        // Biot-Savart: dB = (μ₀/4π) * (I·dl × r̂) / r²
        $rHat = $rMag > 0 ? $r->scale(1/$rMag) : new Vector3(0, 0, 1);
        $crossProduct = $Idl->cross($rHat);
        
        $coefficient = (MU_0 / (4 * M_PI)) / ($rReg**2);
        
        return $crossProduct->scale($coefficient);
    }
    
    /**
     * Magnetic field from circular current loop on Earth's surface
     * Uses geodesic segmentation for accuracy
     */
    public static function geoCircularLoop(
        float $centerLat, float $centerLon, float $alt,
        float $radius,        // meters
        float $turns,
        float $current,
        float $fieldLat, float $fieldLon, float $fieldAlt,
        int $segments = 72,
        float $bufferScale = 1.0
    ): Vector3 {
        $totalField = new Vector3(0, 0, 0);
        $segmentAngle = 2 * M_PI / $segments;
        $segmentLength = (2 * M_PI * $radius) / $segments;
        
        // Center in ECEF
        $center = GeodesicCalculations::geo2ecef($centerLat, $centerLon, $alt);
        
        // Create local coordinate system for the loop
        // Normal is radial outward from Earth center
        $normal = $center->normalize();
        
        // Perpendicular vectors in plane tangent to Earth
        $east = (new Vector3(-sin(deg2rad($centerLon)), cos(deg2rad($centerLon)), 0))->normalize();
        $north = $normal->cross($east)->normalize();
        
        for ($i = 0; $i < $segments; $i++) {
            $midAngle = ($i + 0.5) * $segmentAngle;
            
            // Position on loop in local coordinates
            $localPos = $east->scale($radius * cos($midAngle))
                             ->add($north->scale($radius * sin($midAngle)));
            
            // Convert to ECEF
            $segmentPos = $center->add($localPos);
            
            // Convert back to geo for the function call
            $segmentGeo = GeodesicCalculations::ecef2geo($segmentPos);
            
            // Tangent direction
            $tangent = $east->scale(-sin($midAngle))
                            ->add($north->scale(cos($midAngle)))
                            ->normalize();
            
            // Calculate field contribution
            $dB = self::biotSavartSpherical(
                $segmentGeo['lat'], $segmentGeo['lon'], $segmentGeo['alt'],
                $fieldLat, $fieldLon, $fieldAlt,
                $current * $turns,
                $tangent,
                $segmentLength,
                $bufferScale
            );
            
            $totalField = $totalField->add($dB);
        }
        
        return $totalField;
    }
    
    /**
     * Calculate field grid in geographic coordinates
     * For MGAIA 7-point system visualization
     */
    public static function calculateGeoFieldGrid(
        array $sources,      // Array of source configurations
        float $latMin, float $latMax,
        float $lonMin, float $lonMax,
        float $altitude,
        int $resolution = 20,
        float $bufferScale = 1.0
    ): array {
        $points = [];
        
        $latStep = ($latMax - $latMin) / $resolution;
        $lonStep = ($lonMax - $lonMin) / $resolution;
        
        for ($i = 0; $i <= $resolution; $i++) {
            for ($j = 0; $j <= $resolution; $j++) {
                $lat = $latMin + $i * $latStep;
                $lon = $lonMin + $j * $lonStep;
                
                $totalField = new Vector3(0, 0, 0);
                
                foreach ($sources as $source) {
                    if ($source['type'] === 'loop') {
                        $fieldContribution = self::geoCircularLoop(
                            $source['lat'], $source['lon'], $source['alt'],
                            $source['radius'],
                            $source['turns'],
                            $source['current'],
                            $lat, $lon, $altitude,
                            72,
                            $bufferScale
                        );
                        
                        $totalField = $totalField->add($fieldContribution);
                    }
                }
                
                $points[] = [
                    'lat' => $lat,
                    'lon' => $lon,
                    'alt' => $altitude,
                    'field' => [
                        'x' => $totalField->x,
                        'y' => $totalField->y,
                        'z' => $totalField->z
                    ],
                    'magnitude' => $totalField->magnitude()
                ];
            }
        }
        
        return $points;
    }
    
    /**
     * Atmospheric propagation attenuation
     * For ELF/VLF propagation between MGAIA nodes
     */
    public static function atmosphericAttenuation(
        float $frequency,    // Hz
        float $distance,     // meters
        float $altitude      // meters
    ): float {
        // ELF/VLF attenuation in Earth-ionosphere waveguide
        // Simplified model - can be expanded with full mode theory
        
        $wavelength = SPEED_OF_LIGHT / $frequency;
        
        // Skin depth in seawater (worst case for ELF)
        $skinDepth = 503 / sqrt($frequency);
        
        // Attenuation coefficient (dB/Mm for ELF)
        if ($frequency < 3000) { // ELF range
            $attenuation_dB_per_Mm = 2.0;
        } else if ($frequency < 30000) { // VLF range
            $attenuation_dB_per_Mm = 3.0;
        } else {
            $attenuation_dB_per_Mm = 5.0;
        }
        
        $distance_Mm = $distance / 1e6;
        $total_attenuation_dB = $attenuation_dB_per_Mm * $distance_Mm;
        
        // Convert dB to linear factor
        return pow(10, -$total_attenuation_dB / 20);
    }
}
