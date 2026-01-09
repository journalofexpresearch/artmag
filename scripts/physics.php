<?php
//include_once this script
class ThermalModel {
    // Heat generation: Q = I²R
    public static function calculateHeatGeneration($current, $resistance) {
        return $current * $current * $resistance;
    }
    
    // Heat dissipation: Q = (T - T_ambient) / R_thermal
    public static function calculateHeatDissipation($temp, $ambientTemp, $thermalResistance) {
        return ($temp - $ambientTemp) / $thermalResistance;
    }
    
    // Temperature evolution: dT/dt = (Q_gen - Q_diss) / C_thermal
    public static function updateTemperature($currentTemp, $heatGen, $thermalProps, $deltaTime) {
        $dissipation = self::calculateHeatDissipation(
            $currentTemp,
            $thermalProps['ambientTemperature'],
            $thermalProps['thermalResistance']
        );

        $netHeat = $heatGen - $dissipation;
        $tempChange = ($netHeat * $deltaTime) / $thermalProps['thermalMass'];

        return $currentTemp + $tempChange;
    }

    /**
     * Get default thermal properties for a component type and material
     *
     * @param string $type Component type (resistor, coil, etc.)
     * @param string $material Material type (copper, aluminum, iron, air)
     * @return array Thermal properties
     */
    public static function getDefaultThermalProperties($type, $material = 'copper') {
        // Get material properties from constants
        if (!isset(MATERIAL_PROPERTIES[$material])) {
            $material = 'copper'; // Default fallback
        }

        $matProps = MATERIAL_PROPERTIES[$material];

        // Base thermal properties
        $ambientTemperature = 25.0; // °C
        $maxTemperature = $matProps['maxTemp'] ?? 200.0;

        // Component-specific thermal characteristics
        // Thermal resistance (°C/W) - lower means better heat dissipation
        // Thermal mass (J/°C) - higher means slower temperature changes

        switch ($type) {
            case 'resistor':
                $thermalResistance = 50.0;  // Medium heat dissipation
                $thermalMass = 1.0;         // Small thermal mass
                break;

            case 'wire':
                $thermalResistance = 10.0;  // Good heat dissipation
                $thermalMass = 0.5;         // Very small thermal mass
                break;

            case 'coil':
            case 'inductor':
            case 'solenoid':
            case 'toroid':
            case 'helmholtz':
                $thermalResistance = 30.0;  // Moderate heat dissipation
                $thermalMass = 5.0;         // Larger thermal mass (more wire)
                break;

            case 'transformer':
                $thermalResistance = 40.0;  // Lower heat dissipation (enclosed)
                $thermalMass = 10.0;        // Large thermal mass
                break;

            case 'transistor':
                $thermalResistance = 100.0; // Poor heat dissipation without heatsink
                $thermalMass = 0.2;         // Tiny thermal mass (heats up fast)
                $maxTemperature = 150.0;    // Lower max temp
                break;

            case 'relay':
            case 'switch':
                $thermalResistance = 80.0;  // Moderate heat dissipation
                $thermalMass = 2.0;         // Small thermal mass
                break;

            case 'dcSource':
            case 'acSource':
            case 'pulseGenerator':
                $thermalResistance = 20.0;  // Good heat dissipation (power supply)
                $thermalMass = 20.0;        // Large thermal mass
                $maxTemperature = 100.0;    // Conservative limit
                break;

            case 'capacitor':
                $thermalResistance = 60.0;  // Moderate heat dissipation
                $thermalMass = 3.0;         // Medium thermal mass
                $maxTemperature = 85.0;     // Electrolytic cap limit
                break;

            default:
                // Generic component defaults
                $thermalResistance = 50.0;
                $thermalMass = 1.0;
                break;
        }

        return [
            'ambientTemperature' => $ambientTemperature,
            'thermalResistance' => $thermalResistance,
            'thermalMass' => $thermalMass,
            'maxTemperature' => $maxTemperature,
            'material' => $material
        ];
    }

    /**
     * Get thermal state information for a component
     *
     * @param float $temperature Current temperature (°C)
     * @param float $powerDissipation Power dissipation (W)
     * @param array $thermalProps Thermal properties
     * @return array Thermal state
     */
    public static function getThermalState($temperature, $powerDissipation, $thermalProps) {
        $maxTemp = $thermalProps['maxTemperature'];
        $warningTemp = $maxTemp * 0.75;  // Warning at 75% of max
        $criticalTemp = $maxTemp * 0.90; // Critical at 90% of max

        $isOverheating = $temperature >= $warningTemp;
        $isFailed = $temperature >= $maxTemp;

        // Calculate thermal stress (0.0 to 1.0+)
        $thermalStress = $temperature / $maxTemp;

        // Estimate time to failure if current power dissipation continues
        $steadyStateTemp = $thermalProps['ambientTemperature'] +
                          ($powerDissipation * $thermalProps['thermalResistance']);

        $willOverheat = $steadyStateTemp >= $warningTemp;
        $willFail = $steadyStateTemp >= $maxTemp;

        return [
            'temperature' => $temperature,
            'maxTemperature' => $maxTemp,
            'warningTemperature' => $warningTemp,
            'criticalTemperature' => $criticalTemp,
            'isOverheating' => $isOverheating,
            'isFailed' => $isFailed,
            'thermalStress' => $thermalStress,
            'steadyStateTemp' => $steadyStateTemp,
            'willOverheat' => $willOverheat,
            'willFail' => $willFail,
            'powerDissipation' => $powerDissipation
        ];
    }
}
?>
