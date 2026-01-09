<?php
session_start();
require_once __DIR__ . '/circuit_state.php';

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
        
        default:
            echo json_encode(['error' => 'Unknown action']);
    }
    
    $_SESSION['circuit_state'] = $state;
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}?>
