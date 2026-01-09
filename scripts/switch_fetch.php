<?php
session_start();
require_once __DIR__ . '/circuit_states.php';

/**
 * Simple URI-encoded request handler
 * Just GET/POST params, returns data for direct page insertion
 */

// Get or create state
if (!isset($_SESSION['circuit_state'])) {
    $_SESSION['circuit_state'] = new CircuitState();
}
$state = $_SESSION['circuit_state'];

// Get action from URL
$action = $_GET['action'] ?? $_POST['action'] ?? 'get_state';

// Simple response - just the data, or JSON if complex
try {
    switch ($action) {
        
        // Add component
        case 'add_component':
            $type = $_GET['type'] ?? $_POST['type'];
            $x = $_GET['x'] ?? $_POST['x'] ?? 100;
            $y = $_GET['y'] ?? $_POST['y'] ?? 100;
            
            $comp = $state->addComponent($type, ['x' => $x, 'y' => $y]);
            echo json_encode($comp->toArray());
            break;
        
        // Remove component
        case 'remove_component':
            $id = $_GET['id'] ?? $_POST['id'];
            $state->removeComponent($id);
            echo json_encode(['success' => true, 'id' => $id]);
            break;
        
        // Move component
        case 'move_component':
            $id = $_GET['id'] ?? $_POST['id'];
            $x = $_GET['x'] ?? $_POST['x'];
            $y = $_GET['y'] ?? $_POST['y'];
            
            $state->moveComponent($id, ['x' => $x, 'y' => $y]);
            echo json_encode(['id' => $id, 'x' => $x, 'y' => $y]);
            break;
        
        // Update component property
        case 'update_prop':
            $id = $_GET['id'] ?? $_POST['id'];
            $prop = $_GET['prop'] ?? $_POST['prop'];
            $value = $_GET['value'] ?? $_POST['value'];
            
            $state->updateComponent($id, [$prop => $value]);
            echo json_encode(['id' => $id, $prop => $value]);
            break;
        
        // Add wire
        case 'add_wire':
            $startComp = $_GET['start_comp'] ?? $_POST['start_comp'];
            $startPort = $_GET['start_port'] ?? $_POST['start_port'];
            $endComp = $_GET['end_comp'] ?? $_POST['end_comp'];
            $endPort = $_GET['end_port'] ?? $_POST['end_port'];
            
            $wire = $state->addWire($startComp, $startPort, $endComp, $endPort);
            echo json_encode(['id' => $wire->id]);
            break;
        
        // Select component
        case 'select':
            $id = $_GET['id'] ?? $_POST['id'] ?? null;
            $state->selectComponent($id);
            echo json_encode(['selected' => $id]);
            break;
        
        // Start simulation
        case 'sim_start':
            $state->startSimulation();
            echo json_encode(['running' => $state->simulation->isRunning]);
            break;
        
        // Stop simulation
        case 'sim_stop':
            $state->stopSimulation();
            echo json_encode(['running' => false]);
            break;
        
        // Step simulation
        case 'sim_step':
            $dt = $_GET['dt'] ?? $_POST['dt'] ?? 0.001;
            $state->stepSimulation($dt);
            
            // Return updated component temps
            $temps = [];
            foreach ($state->project->components as $c) {
                $temps[$c->id] = [
                    'temp' => $c->temperature,
                    'warning' => $c->warningLevel
                ];
            }
            echo json_encode(['time' => $state->simulation->time, 'components' => $temps]);
            break;
        
        // Get all components (for rendering)
        case 'get_components':
            $comps = array_map(fn($c) => $c->toArray(), $state->project->components);
            echo json_encode($comps);
            break;
        
        // Get all wires
        case 'get_wires':
            $wires = array_map(fn($w) => [
                'id' => $w->id,
                'start' => $w->startComponentId,
                'end' => $w->endComponentId
            ], $state->project->wires);
            echo json_encode($wires);
            break;
        
        // Get full state
        case 'get_state':
            echo json_encode($state->toArray());
            break;
        
        // Calculate magnetic field at point
        case 'calc_field':
            require_once __DIR__ . '/magnetic_field.php';

            $lat = floatval($_GET['lat'] ?? 0);
            $lon = floatval($_GET['lon'] ?? 0);
            $alt = floatval($_GET['alt'] ?? 0);

            // Collect all magnetic sources from circuit components
            $sources = [];
            foreach ($state->project->components as $comp) {
                if ($comp->category === 'magnetic' && !$comp->isFailed) {
                    switch ($comp->type) {
                        case 'coil':
                            $sources[] = [
                                'type' => 'loop',
                                'lat' => $comp->properties->latitude ?? 0,
                                'lon' => $comp->properties->longitude ?? 0,
                                'alt' => $comp->properties->altitude ?? 0,
                                'radius' => $comp->properties->radius ?? 0.01,
                                'turns' => $comp->properties->turns ?? 100,
                                'current' => $comp->currentFlow
                            ];
                            break;
                    }
                }
            }

            // Calculate total field at point
            $totalField = new Vector3(0, 0, 0);
            foreach ($sources as $source) {
                if ($source['type'] === 'loop') {
                    $fieldContribution = SphericalMagneticField::geoCircularLoop(
                        $source['lat'], $source['lon'], $source['alt'],
                        $source['radius'],
                        $source['turns'],
                        $source['current'],
                        $lat, $lon, $alt,
                        72,  // segments
                        1.0  // buffer scale
                    );
                    $totalField = $totalField->add($fieldContribution);
                }
            }

            echo json_encode([
                'field' => [
                    'x' => $totalField->x,
                    'y' => $totalField->y,
                    'z' => $totalField->z
                ],
                'magnitude' => $totalField->magnitude()
            ]);
            break;

        // Calculate 3D magnetic field grid
        case 'calc_field_grid':
            require_once __DIR__ . '/magnetic_field.php';

            // Get grid parameters
            $xMin = floatval($_GET['xMin'] ?? -1);
            $xMax = floatval($_GET['xMax'] ?? 1);
            $yMin = floatval($_GET['yMin'] ?? -1);
            $yMax = floatval($_GET['yMax'] ?? 1);
            $zMin = floatval($_GET['zMin'] ?? -1);
            $zMax = floatval($_GET['zMax'] ?? 1);
            $resolution = intval($_GET['resolution'] ?? 10);
            $centerLat = floatval($_GET['centerLat'] ?? 0);
            $centerLon = floatval($_GET['centerLon'] ?? 0);
            $centerAlt = floatval($_GET['centerAlt'] ?? 0);

            // Clamp resolution to prevent overload
            $resolution = max(5, min($resolution, 30));

            // Collect all magnetic sources from circuit components
            $sources = [];
            foreach ($state->project->components as $comp) {
                if ($comp->category === 'magnetic' && !$comp->isFailed && $comp->currentFlow > 1e-9) {
                    $compLat = $comp->properties->latitude ?? $centerLat;
                    $compLon = $comp->properties->longitude ?? $centerLon;
                    $compAlt = $comp->properties->altitude ?? $centerAlt;

                    switch ($comp->type) {
                        case 'coil':
                            $sources[] = [
                                'type' => 'loop',
                                'lat' => $compLat,
                                'lon' => $compLon,
                                'alt' => $compAlt,
                                'radius' => $comp->properties->radius ?? 0.01,
                                'turns' => $comp->properties->turns ?? 100,
                                'current' => $comp->currentFlow,
                                'componentId' => $comp->id
                            ];
                            break;

                        case 'solenoid':
                            // Solenoid approximation: multiple stacked loops
                            $length = $comp->properties->length ?? 0.1;
                            $radius = $comp->properties->radius ?? 0.02;
                            $turns = $comp->properties->turns ?? 500;
                            $loopSpacing = $length / max($turns, 1);

                            // Sample every Nth turn to avoid too many sources
                            $sampleRate = max(1, intval($turns / 20));
                            for ($i = 0; $i < $turns; $i += $sampleRate) {
                                $zOffset = ($i / max($turns - 1, 1) - 0.5) * $length;
                                $sources[] = [
                                    'type' => 'loop',
                                    'lat' => $compLat,
                                    'lon' => $compLon,
                                    'alt' => $compAlt + $zOffset,
                                    'radius' => $radius,
                                    'turns' => $sampleRate,
                                    'current' => $comp->currentFlow,
                                    'componentId' => $comp->id
                                ];
                            }
                            break;

                        case 'helmholtz':
                            $radius = $comp->properties->radius ?? 0.1;
                            $turns = $comp->properties->turns ?? 100;
                            $separation = $comp->properties->separation ?? 0.1;

                            // Two coils separated along z-axis
                            $sources[] = [
                                'type' => 'loop',
                                'lat' => $compLat,
                                'lon' => $compLon,
                                'alt' => $compAlt - $separation/2,
                                'radius' => $radius,
                                'turns' => $turns,
                                'current' => $comp->currentFlow,
                                'componentId' => $comp->id . '_coil1'
                            ];
                            $sources[] = [
                                'type' => 'loop',
                                'lat' => $compLat,
                                'lon' => $compLon,
                                'alt' => $compAlt + $separation/2,
                                'radius' => $radius,
                                'turns' => $turns,
                                'current' => $comp->currentFlow,
                                'componentId' => $comp->id . '_coil2'
                            ];
                            break;
                    }
                }
            }

            // Calculate field grid
            $gridPoints = [];
            $xStep = ($xMax - $xMin) / max($resolution - 1, 1);
            $yStep = ($yMax - $yMin) / max($resolution - 1, 1);
            $zStep = ($zMax - $zMin) / max($resolution - 1, 1);

            for ($i = 0; $i < $resolution; $i++) {
                for ($j = 0; $j < $resolution; $j++) {
                    for ($k = 0; $k < $resolution; $k++) {
                        $x = $xMin + $i * $xStep;
                        $y = $yMin + $j * $yStep;
                        $z = $zMin + $k * $zStep;

                        // Calculate field at this point
                        $totalField = new Vector3(0, 0, 0);

                        foreach ($sources as $source) {
                            if ($source['type'] === 'loop') {
                                // Use local Cartesian approximation for small scales
                                $fieldContribution = SphericalMagneticField::geoCircularLoop(
                                    $source['lat'],
                                    $source['lon'],
                                    $source['alt'] + $z,
                                    $source['radius'],
                                    $source['turns'],
                                    $source['current'],
                                    $centerLat,
                                    $centerLon,
                                    $centerAlt + $z,
                                    36,  // Fewer segments for grid calculation
                                    1.0
                                );
                                $totalField = $totalField->add($fieldContribution);
                            }
                        }

                        $magnitude = $totalField->magnitude();

                        // Only include points with significant field strength
                        if ($magnitude > 1e-15) {
                            $gridPoints[] = [
                                'position' => ['x' => $x, 'y' => $y, 'z' => $z],
                                'field' => [
                                    'x' => $totalField->x,
                                    'y' => $totalField->y,
                                    'z' => $totalField->z
                                ],
                                'magnitude' => $magnitude
                            ];
                        }
                    }
                }
            }

            echo json_encode([
                'grid' => $gridPoints,
                'resolution' => $resolution,
                'bounds' => [
                    'xMin' => $xMin, 'xMax' => $xMax,
                    'yMin' => $yMin, 'yMax' => $yMax,
                    'zMin' => $zMin, 'zMax' => $zMax
                ],
                'sources' => array_map(function($s) {
                    return [
                        'type' => $s['type'],
                        'position' => [
                            'lat' => $s['lat'],
                            'lon' => $s['lon'],
                            'alt' => $s['alt']
                        ],
                        'radius' => $s['radius'] ?? 0,
                        'current' => $s['current'] ?? 0
                    ];
                }, $sources),
                'totalPoints' => count($gridPoints)
            ]);
            break;

        default:
            echo json_encode(['error' => 'Unknown action']);
    }
    
    $_SESSION['circuit_state'] = $state;
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}?>
