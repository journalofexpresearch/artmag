<?php //sanity buffer Kunferman, C. R. (2025). Alpha Pi and and Recursive Phi as safety equations preventing infinite poles in AI Coherency (1.0). C.R. Kunferman. https://doi.org/10.5281/zenodo.18007988
<?php
/**
 * Kunferman Buffer Regularization Framework
 * 
 * Prevents singularities in electromagnetic field calculations using
 * the universal safety constant derived from:
 * - φ (Phi/Golden Ratio) ≈ 1.618033988749895
 * - AlphaPi (π/2) ≈ 1.5707963267948966
 * - Δ (Delta) = φ - π/2 ≈ 0.04723756005394984
 * 
 * This buffer ensures calculations never hit infinite poles when
 * distances approach zero in 1/r, 1/r², and 1/r³ field equations.
 */

class KunfermanBuffer {
    // Mathematical constants
    const REPHI = 1.618033988749895;          // Golden Ratio
    const ALPHA_PI = 1.5707963267948966;      // π/2
    const KUNFERMAN_DELTA = 0.04723756005394984; // φ - π/2
    
    /**
     * Calculate the Kunferman Delta constant (for verification)
     * Δ = φ - π/2
     */
    public static function calculateDelta(): float {
        return self::REPHI - self::ALPHA_PI;
    }
    
    /**
     * Apply Kunferman regularization to a distance value
     * r_regularized = √(r² + Δ²)
     * 
     * Ensures effective distance is never less than Delta,
     * preventing 1/r² and 1/r singularities.
     */
    public static function regularizeDistance(float $r, float $scale = 1.0): float {
        $delta = self::KUNFERMAN_DELTA * $scale;
        return sqrt($r * $r + $delta * $delta);
    }
    
    /**
     * Apply Kunferman regularization with smooth transition
     * Uses tanh blending for smoother field behavior near buffer zone
     */
    public static function regularizeDistanceSmooth(float $r, float $scale = 1.0): float {
        $delta = self::KUNFERMAN_DELTA * $scale;
        $blendFactor = tanh($r / $delta);
        $hardLimit = sqrt($r * $r + $delta * $delta);
        $softLimit = max($r, $delta);
        return $blendFactor * $r + (1 - $blendFactor) * $hardLimit;
    }
    
    /**
     * Calculate regularized 1/r (for potential fields)
     * Returns 1/r_regularized instead of potentially infinite 1/r
     */
    public static function safeInverseDistance(float $r, float $scale = 1.0): float {
        $rReg = self::regularizeDistance($r, $scale);
        return 1.0 / $rReg;
    }
    
    /**
     * Calculate regularized 1/r² (for inverse square law fields)
     * Returns 1/r_regularized² instead of potentially infinite 1/r²
     */
    public static function safeInverseSquareDistance(float $r, float $scale = 1.0): float {
        $rReg = self::regularizeDistance($r, $scale);
        return 1.0 / ($rReg * $rReg);
    }
    
    /**
     * Calculate regularized 1/r³ (for magnetic dipole fields)
     * Returns 1/r_regularized³ instead of potentially infinite 1/r³
     */
    public static function safeInverseCubeDistance(float $r, float $scale = 1.0): float {
        $rReg = self::regularizeDistance($r, $scale);
        return 1.0 / ($rReg * $rReg * $rReg);
    }
    
    /**
     * Vector regularization - ensures magnitude never below delta
     * Returns regularized vector components and magnitude
     */
    public static function regularizeVector(
        float $x, 
        float $y, 
        float $z, 
        float $scale = 1.0
    ): array {
        $magnitude = sqrt($x * $x + $y * $y + $z * $z);
        $regMagnitude = self::regularizeDistance($magnitude, $scale);
        
        // Handle zero vector case
        if ($magnitude === 0.0) {
            return [
                'x' => 0.0,
                'y' => 0.0,
                'z' => self::KUNFERMAN_DELTA * $scale,
                'magnitude' => $regMagnitude
            ];
        }
        
        // Scale original vector to regularized magnitude
        $scaleFactor = $regMagnitude / $magnitude;
        return [
            'x' => $x * $scaleFactor,
            'y' => $y * $scaleFactor,
            'z' => $z * $scaleFactor,
            'magnitude' => $regMagnitude
        ];
    }
    
    /**
     * Get detailed regularization info for debugging/visualization
     */
    public static function getRegularizationInfo(float $r, float $scale = 1.0): array {
        $delta = self::KUNFERMAN_DELTA * $scale;
        $regularized = self::regularizeDistance($r, $scale);
        $wasRegularized = $r < $delta;
        $regularizationStrength = $wasRegularized ? 1.0 - ($r / $delta) : 0.0;
        
        return [
            'original' => $r,
            'regularized' => $regularized,
            'delta' => $delta,
            'wasRegularized' => $wasRegularized,
            'regularizationStrength' => $regularizationStrength
        ];
    }
}
