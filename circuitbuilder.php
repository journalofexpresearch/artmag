<script>
// Add component
async function addComponent(type, x, y) {
    const response = await fetch(`scripts/switch_fetch.php?action=add_component&type=${type}&x=${x}&y=${y}`);
    const data = await response.json();
    
    // Put on page
    const div = document.createElement('div');
    div.id = data.id;
    div.className = 'component';
    div.style.left = data.position.x + 'px';
    div.style.top = data.position.y + 'px';
    div.innerHTML = data.name;
    
    document.getElementById('canvas').appendChild(div);
}

// Move component
async function moveComponent(id, x, y) {
    await fetch(`scripts/switch_fetch.php?action=move_component&id=${id}&x=${x}&y=${y}`);
    
    // Update on page
    const elem = document.getElementById(id);
    elem.style.left = x + 'px';
    elem.style.top = y + 'px';
}

// Update property (like resistance value)
async function updateProperty(id, prop, value) {
    await fetch(`scripts/switch_fetch.php?action=update_prop&id=${id}&prop=${prop}&value=${value}`);
    
    // Update display
    document.getElementById(id + '_' + prop).textContent = value;
}

// Start simulation
async function startSim() {
    const response = await fetch('scripts/switch_fetch.php?action=sim_start');
    const data = await response.json();
    
    if (data.running) {
        document.getElementById('status').textContent = 'Running';
        requestAnimationFrame(simLoop);
    }
}

// Simulation loop
async function simLoop() {
    const response = await fetch('scripts/switch_fetch.php?action=sim_step&dt=0.001');
    const data = await response.json();
    
    // Update component displays
    for (let id in data.components) {
        const elem = document.getElementById(id);
        if (elem) {
            elem.style.backgroundColor = getTempColor(data.components[id].temp);
        }
    }
    
    requestAnimationFrame(simLoop);
}
</script>
