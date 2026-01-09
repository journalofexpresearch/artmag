<?php 
require_once __DIR__ . '/inc/header.php';
?>

<style>
  * { margin:0; padding:0; box-sizing:border-box; }

  body {
    height: 100vh;
    overflow: hidden;
    background: #000;
    color: white;
    font-family: system-ui, sans-serif;
    perspective: 1400px;
  }

  .room {
    display: grid;
    height: 100vh;
    width: 100vw;
    padding: 6px;
    gap: 6px;
    background: #111;
    grid-template-columns: 20% 1fr 20%;
    grid-template-rows: 19% 1fr 19%;
    grid-template-areas:
      "top    top    top"
      "left   mid    right"
      "bottom bottom bottom";
  }

  .top    { grid-area: top; }
  .left   { grid-area: left; }
  .mid    { grid-area: mid; }
  .right  { grid-area: right; }
  .bottom { grid-area: bottom; }

  .panel {
    background: #606066 center/cover no-repeat;
    border-radius: 10px;
    overflow: hidden;
    position: relative;
    transition: all 0.7s cubic-bezier(0.4, 0, 0.2, 1);
    transform-style: preserve-3d;
  }

  .mid {
    padding: 0;
    background-color: rgba(33,33,33,0.65) !important;
    overflow: hidden;
    position: relative;
  }

  .panel:hover {
    transform: translateZ(180px) scale(1.08);
    z-index: 100;
    box-shadow: 0 40px 100px rgba(0,255,255,0.7);
  }

  .top:hover, .bottom:hover { grid-column: 1 / 4; }
  .left:hover, .right:hover { grid-row: 1 / 4; grid-column: span 2; }

  .panel::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(0,255,255,0.15), transparent 70%);
    opacity: 0;
    transition: opacity 0.7s;
    pointer-events: none;
  }
  .panel:hover::after { opacity: 1; }

  /* Component Palette (Top) */
  #component-palette {
    padding: 10px;
    overflow-x: auto;
    overflow-y: hidden;
    display: flex;
    gap: 10px;
    align-items: center;
  }

  .component-item {
    min-width: 60px;
    height: 60px;
    background: rgba(255,255,255,0.1);
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: grab;
    font-size: 24px;
    transition: all 0.3s;
  }

  .component-item:hover {
    background: rgba(0,255,255,0.2);
    transform: scale(1.1);
  }

  .component-item span {
    font-size: 10px;
    margin-top: 4px;
  }

  /* Properties Panel (Left) */
  #properties-panel {
    padding: 20px;
    overflow-y: auto;
    font-size: 14px;
  }

  #properties-panel h3 {
    color: #0ff;
    margin-bottom: 10px;
    font-size: 16px;
  }

  .prop-group {
    margin-bottom: 15px;
  }

  .prop-group label {
    display: block;
    color: #aaa;
    font-size: 12px;
    margin-bottom: 4px;
  }

  .prop-group input {
    width: 100%;
    padding: 6px;
    background: rgba(255,255,255,0.1);
    border: 1px solid #444;
    border-radius: 4px;
    color: white;
  }

  /* Canvas (Middle) */
  #circuit-canvas {
    width: 100%;
    height: 100%;
    position: relative;
    background: 
      linear-gradient(rgba(0,255,255,0.02) 1px, transparent 1px),
      linear-gradient(90deg, rgba(0,255,255,0.02) 1px, transparent 1px);
    background-size: 20px 20px;
  }

  .circuit-component {
    position: absolute;
    background: rgba(255,255,255,0.1);
    border: 2px solid #0ff;
    border-radius: 6px;
    padding: 8px;
    cursor: move;
    font-size: 12px;
    text-align: center;
    min-width: 60px;
    transition: all 0.2s;
  }

  .circuit-component:hover {
    background: rgba(0,255,255,0.2);
    box-shadow: 0 0 20px rgba(0,255,255,0.5);
  }

  .circuit-component.selected {
    border-color: #ff0;
    box-shadow: 0 0 20px rgba(255,255,0,0.5);
  }

  /* Simulation Controls (Right) */
  #sim-controls {
    padding: 20px;
    overflow-y: auto;
  }

  #sim-controls h3 {
    color: #0ff;
    margin-bottom: 15px;
  }

  .control-btn {
    width: 100%;
    padding: 10px;
    margin-bottom: 10px;
    background: rgba(0,255,255,0.2);
    border: 1px solid #0ff;
    border-radius: 6px;
    color: white;
    cursor: pointer;
    transition: all 0.3s;
  }

  .control-btn:hover {
    background: rgba(0,255,255,0.4);
    box-shadow: 0 0 15px rgba(0,255,255,0.5);
  }

  .control-btn.active {
    background: rgba(0,255,0,0.3);
    border-color: #0f0;
  }

  .readout {
    background: rgba(0,0,0,0.3);
    padding: 10px;
    border-radius: 6px;
    margin-bottom: 10px;
    font-family: monospace;
    font-size: 12px;
  }

  /* Status Bar (Bottom) */
  #status-bar {
    padding: 10px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 12px;
  }

  .status-item {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .status-indicator {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #0f0;
    animation: pulse 2s infinite;
  }

  @keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.3; }
  }
</style>

<div class="room">
  <!-- Top: Component Palette -->
  <div class="panel top">
    <div id="component-palette">
      <div class="component-item" draggable="true" data-type="resistor">
        <div>⏛</div>
        <span>Resistor</span>
      </div>
      <div class="component-item" draggable="true" data-type="capacitor">
        <div>⊣⊢</div>
        <span>Capacitor</span>
      </div>
      <div class="component-item" draggable="true" data-type="inductor">
        <div>⌇</div>
        <span>Inductor</span>
      </div>
      <div class="component-item" draggable="true" data-type="dcSource">
        <div>⊕</div>
        <span>DC Source</span>
      </div>
      <div class="component-item" draggable="true" data-type="coil">
        <div>◎</div>
        <span>Coil</span>
      </div>
      <div class="component-item" draggable="true" data-type="solenoid">
        <div>⌬</div>
        <span>Solenoid</span>
      </div>
      <div class="component-item" draggable="true" data-type="ground">
        <div>⏚</div>
        <span>Ground</span>
      </div>
    </div>
  </div>

  <!-- Left: Properties Panel -->
  <div class="panel left">
    <div id="properties-panel">
      <h3>Component Properties</h3>
      <div id="prop-content">
        <p style="color: #666; font-style: italic;">Select a component to edit properties</p>
      </div>
    </div>
  </div>

  <!-- Middle: Circuit Canvas -->
  <div class="panel mid">
    <div id="circuit-canvas"></div>
  </div>

  <!-- Right: Simulation Controls -->
  <div class="panel right">
    <div id="sim-controls">
      <h3>Simulation</h3>
      <button class="control-btn" id="btn-start">▶ Start</button>
      <button class="control-btn" id="btn-stop">⏹ Stop</button>
      <button class="control-btn" id="btn-reset">↻ Reset</button>
      
      <div style="margin-top: 20px;">
        <h3 style="font-size: 14px;">Readouts</h3>
        <div class="readout">
          <div>Time: <span id="sim-time">0.000</span>s</div>
          <div>Components: <span id="comp-count">0</span></div>
          <div>Circuit: <span id="circuit-status">Incomplete</span></div>
        </div>
      </div>
      
      <div style="margin-top: 20px;">
        <h3 style="font-size: 14px;">View Mode</h3>
        <button class="control-btn" id="btn-schematic">Schematic</button>
        <button class="control-btn" id="btn-field3d">3D Field</button>
        <button class="control-btn" id="btn-largescale">Large Scale</button>
      </div>
    </div>
  </div>

  <!-- Bottom: Status Bar -->
  <div class="panel bottom">
    <div id="status-bar">
      <div class="status-item">
        <div class="status-indicator"></div>
        <span>Ready</span>
      </div>
      <div class="status-item">
        <span id="status-message">Drag components onto canvas to begin</span>
      </div>
      <div class="status-item">
        <span>Buffer: Kunferman Δ = 0.0538</span>
      </div>
    </div>
  </div>
</div>

<script>
// Component drag and drop
document.querySelectorAll('.component-item').forEach(item => {
  item.addEventListener('dragstart', (e) => {
    e.dataTransfer.setData('componentType', e.currentTarget.dataset.type);
    e.dataTransfer.effectAllowed = 'copy';
  });
});

const canvas = document.getElementById('circuit-canvas');
let selectedComponentId = null;

// Canvas drop handler
canvas.addEventListener('dragover', (e) => {
  e.preventDefault();
  e.dataTransfer.dropEffect = 'copy';
});

// Helper function to attach event handlers to a component
function attachComponentHandlers(comp) {
  comp.addEventListener('click', () => selectComponent(comp.dataset.id));

  comp.addEventListener('mousedown', (e) => {
    draggedComponent = comp;
    const rect = comp.getBoundingClientRect();
    dragOffsetX = e.clientX - rect.left;
    dragOffsetY = e.clientY - rect.top;
    e.stopPropagation();
  });
}

canvas.addEventListener('drop', async (e) => {
  e.preventDefault();
  const type = e.dataTransfer.getData('componentType');
  const rect = canvas.getBoundingClientRect();
  const x = e.clientX - rect.left;
  const y = e.clientY - rect.top;

  // Fetch server-rendered component HTML
  const response = await fetch(`scripts/switch_fetch.php?action=add_component&type=${type}&x=${x}&y=${y}`);
  const html = await response.text();

  // Insert into canvas
  canvas.insertAdjacentHTML('beforeend', html);

  // Attach event listeners to new component
  const newComp = canvas.lastElementChild;
  if (newComp) {
    attachComponentHandlers(newComp);
  }

  updateComponentCount();
});

// Select component and render properties
async function selectComponent(id) {
  document.querySelectorAll('.circuit-component').forEach(c => c.classList.remove('selected'));
  document.getElementById(id)?.classList.add('selected');

  selectedComponentId = id;

  // Fetch server-rendered properties HTML
  const response = await fetch(`scripts/switch_fetch.php?action=render_properties&id=${id}`);
  const html = await response.text();

  document.getElementById('prop-content').innerHTML = html;

  // Tell backend about selection
  await fetch(`scripts/switch_fetch.php?action=select&id=${id}`);
}

// Update component property
async function updateProperty(id, prop, value) {
  await fetch(`scripts/switch_fetch.php?action=update_prop&id=${id}&prop=${prop}&value=${value}`);
}

// Simulation controls
document.getElementById('btn-start').addEventListener('click', async () => {
  const response = await fetch('scripts/switch_fetch.php?action=sim_start');
  const data = await response.json();
  
  if (data.running) {
    document.getElementById('btn-start').classList.add('active');
    document.getElementById('status-message').textContent = 'Simulation running...';
    startSimulationLoop();
  }
});

document.getElementById('btn-stop').addEventListener('click', async () => {
  simRunning = false; // Stop the simulation loop
  await fetch('scripts/switch_fetch.php?action=sim_stop');
  document.getElementById('btn-start').classList.remove('active');
  document.getElementById('status-message').textContent = 'Simulation stopped';
});

document.getElementById('btn-reset').addEventListener('click', async () => {
  await fetch('scripts/switch_fetch.php?action=sim_reset');
  document.getElementById('sim-time').textContent = '0.000';
  document.getElementById('status-message').textContent = 'Simulation reset';
});

// Simulation loop - re-render canvas from server periodically
let simRunning = false;
async function startSimulationLoop() {
  simRunning = true;

  async function loop() {
    if (!simRunning) return;

    // Step simulation on server
    const response = await fetch('scripts/switch_fetch.php?action=sim_step&dt=0.01');
    const data = await response.json();

    // Update display
    document.getElementById('sim-time').textContent = data.time.toFixed(3);

    // Re-render canvas from server to show updated component states
    renderCanvas();

    setTimeout(loop, 100); // 10 FPS (server rendering is heavier)
  }

  loop();
}

// Component dragging state
let draggedComponent = null;
let dragOffsetX = 0;
let dragOffsetY = 0;

// Re-render entire canvas from server
async function renderCanvas() {
  const response = await fetch('scripts/switch_fetch.php?action=render_canvas');
  const html = await response.text();

  canvas.innerHTML = html;

  // Attach click and drag handlers to all components
  document.querySelectorAll('.circuit-component').forEach(comp => {
    attachComponentHandlers(comp);
  });

  updateComponentCount();
}

// Canvas drag handlers for moving components
canvas.addEventListener('mousemove', (e) => {
  if (!draggedComponent) return;

  const rect = canvas.getBoundingClientRect();
  const x = e.clientX - rect.left - dragOffsetX;
  const y = e.clientY - rect.top - dragOffsetY;

  // Update position visually (immediate feedback)
  draggedComponent.style.left = x + 'px';
  draggedComponent.style.top = y + 'px';
});

canvas.addEventListener('mouseup', async (e) => {
  if (!draggedComponent) return;

  const rect = canvas.getBoundingClientRect();
  const x = e.clientX - rect.left - dragOffsetX;
  const y = e.clientY - rect.top - dragOffsetY;

  // Update position on server
  await fetch(`scripts/switch_fetch.php?action=move_component&id=${draggedComponent.dataset.id}&x=${x}&y=${y}`);

  draggedComponent = null;
});

// Fallback to clear drag state if mouse released outside canvas
document.addEventListener('mouseup', () => {
  draggedComponent = null;
});

// Update component count
async function updateComponentCount() {
  const response = await fetch('scripts/switch_fetch.php?action=get_state');
  const data = await response.json();
  document.getElementById('comp-count').textContent = data.project.components.length;
  document.getElementById('circuit-status').textContent = data.simulation.isCircuitComplete ? 'Complete' : 'Incomplete';
}

// Initial load - render canvas from server
renderCanvas();
</script>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
