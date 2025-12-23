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
    
    // ... rest follows same pattern
}
?>
