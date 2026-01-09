<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>3D Magnetic Field Viewer</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            overflow: hidden;
        }

        #container {
            display: flex;
            height: 100vh;
        }

        #controls {
            width: 320px;
            background: rgba(20, 20, 40, 0.95);
            color: #fff;
            padding: 20px;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.5);
        }

        #viewer {
            flex: 1;
            position: relative;
        }

        h1 {
            font-size: 22px;
            margin-bottom: 20px;
            color: #4fc3f7;
            border-bottom: 2px solid #4fc3f7;
            padding-bottom: 10px;
        }

        h2 {
            font-size: 16px;
            margin: 20px 0 10px 0;
            color: #81d4fa;
        }

        .control-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-size: 13px;
            color: #b3e5fc;
        }

        input[type="range"] {
            width: 100%;
            margin-bottom: 5px;
        }

        input[type="number"],
        select {
            width: 100%;
            padding: 8px;
            border: 1px solid #555;
            background: rgba(30, 30, 50, 0.8);
            color: #fff;
            border-radius: 4px;
            font-size: 13px;
        }

        button {
            width: 100%;
            padding: 12px;
            margin: 5px 0;
            border: none;
            border-radius: 4px;
            background: linear-gradient(135deg, #4fc3f7 0%, #2196f3 100%);
            color: white;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
        }

        button:hover {
            background: linear-gradient(135deg, #2196f3 0%, #1976d2 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(33, 150, 243, 0.4);
        }

        button:active {
            transform: translateY(0);
        }

        .export-btn {
            background: linear-gradient(135deg, #66bb6a 0%, #43a047 100%);
        }

        .export-btn:hover {
            background: linear-gradient(135deg, #43a047 0%, #2e7d32 100%);
        }

        .value-display {
            display: inline-block;
            float: right;
            color: #4fc3f7;
            font-weight: bold;
        }

        #status {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            background: rgba(76, 175, 80, 0.2);
            border-left: 4px solid #4caf50;
            font-size: 12px;
        }

        #status.error {
            background: rgba(244, 67, 54, 0.2);
            border-left-color: #f44336;
        }

        #status.loading {
            background: rgba(255, 152, 0, 0.2);
            border-left-color: #ff9800;
        }

        .checkbox-group {
            margin: 10px 0;
        }

        .checkbox-group label {
            display: inline-block;
            margin-right: 15px;
            cursor: pointer;
        }

        .checkbox-group input[type="checkbox"] {
            margin-right: 5px;
        }

        #info {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(0, 0, 0, 0.7);
            color: #fff;
            padding: 15px;
            border-radius: 8px;
            font-size: 12px;
            pointer-events: none;
            font-family: 'Courier New', monospace;
        }

        .info-row {
            margin: 3px 0;
        }

        .info-label {
            color: #4fc3f7;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div id="container">
        <div id="controls">
            <h1>🧲 3D Field Viewer</h1>

            <div id="status">Ready to calculate field</div>

            <h2>Grid Settings</h2>
            <div class="control-group">
                <label>
                    Resolution: <span class="value-display" id="resDisplay">15</span>
                </label>
                <input type="range" id="resolution" min="5" max="25" value="15" step="1">
                <small style="color: #888;">Higher = more detail, slower calculation</small>
            </div>

            <div class="control-group">
                <label>
                    Grid Size: <span class="value-display" id="sizeDisplay">1.0m</span>
                </label>
                <input type="range" id="gridSize" min="0.1" max="10" value="1" step="0.1">
            </div>

            <h2>Visualization</h2>
            <div class="control-group">
                <label>Display Mode:</label>
                <select id="displayMode">
                    <option value="vectors">Vector Field</option>
                    <option value="streamlines">Streamlines</option>
                    <option value="magnitude">Magnitude Cloud</option>
                    <option value="combined">Combined</option>
                </select>
            </div>

            <div class="control-group">
                <label>
                    Vector Scale: <span class="value-display" id="scaleDisplay">1.0</span>
                </label>
                <input type="range" id="vectorScale" min="0.1" max="5" value="1" step="0.1">
            </div>

            <div class="control-group">
                <label>
                    Color Intensity: <span class="value-display" id="colorDisplay">1.0</span>
                </label>
                <input type="range" id="colorIntensity" min="0.1" max="3" value="1" step="0.1">
            </div>

            <div class="checkbox-group">
                <label>
                    <input type="checkbox" id="showSources" checked>
                    Show Sources
                </label>
                <label>
                    <input type="checkbox" id="showGrid" checked>
                    Show Grid
                </label>
            </div>

            <h2>Actions</h2>
            <button id="calculateBtn">🔄 Calculate Field</button>
            <button id="resetViewBtn">📷 Reset View</button>

            <h2>Export for Blender</h2>
            <button class="export-btn" id="exportJsonBtn">💾 Export JSON</button>
            <button class="export-btn" id="exportVectorFieldBtn">💾 Export Vector Field (CSV)</button>
            <small style="color: #888; display: block; margin-top: 5px;">
                Import JSON in Blender using custom script or CSV for vector field visualization
            </small>

            <h2>Info</h2>
            <div style="font-size: 11px; color: #aaa; line-height: 1.6;">
                <strong>Controls:</strong><br>
                • Left drag: Rotate<br>
                • Right drag: Pan<br>
                • Scroll: Zoom<br>
                • Double-click: Reset
            </div>
        </div>

        <div id="viewer">
            <div id="info">
                <div class="info-row"><span class="info-label">Points:</span> <span id="pointCount">0</span></div>
                <div class="info-row"><span class="info-label">Sources:</span> <span id="sourceCount">0</span></div>
                <div class="info-row"><span class="info-label">Max Field:</span> <span id="maxField">0</span> T</div>
                <div class="info-row"><span class="info-label">FPS:</span> <span id="fps">0</span></div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js"></script>

    <script>
        // Three.js setup
        let scene, camera, renderer, controls;
        let fieldGroup, sourcesGroup, gridHelper;
        let currentFieldData = null;
        let animationId = null;

        // UI elements
        const resolutionSlider = document.getElementById('resolution');
        const gridSizeSlider = document.getElementById('gridSize');
        const vectorScaleSlider = document.getElementById('vectorScale');
        const colorIntensitySlider = document.getElementById('colorIntensity');
        const displayModeSelect = document.getElementById('displayMode');
        const calculateBtn = document.getElementById('calculateBtn');
        const resetViewBtn = document.getElementById('resetViewBtn');
        const exportJsonBtn = document.getElementById('exportJsonBtn');
        const exportVectorFieldBtn = document.getElementById('exportVectorFieldBtn');
        const showSourcesCheckbox = document.getElementById('showSources');
        const showGridCheckbox = document.getElementById('showGrid');
        const statusDiv = document.getElementById('status');

        function init() {
            // Scene
            scene = new THREE.Scene();
            scene.background = new THREE.Color(0x050520);
            scene.fog = new THREE.Fog(0x050520, 5, 15);

            // Camera
            const viewerDiv = document.getElementById('viewer');
            camera = new THREE.PerspectiveCamera(
                60,
                viewerDiv.clientWidth / viewerDiv.clientHeight,
                0.01,
                100
            );
            camera.position.set(2, 2, 2);

            // Renderer
            renderer = new THREE.WebGLRenderer({ antialias: true });
            renderer.setSize(viewerDiv.clientWidth, viewerDiv.clientHeight);
            renderer.setPixelRatio(window.devicePixelRatio);
            viewerDiv.appendChild(renderer.domElement);

            // Controls
            controls = new THREE.OrbitControls(camera, renderer.domElement);
            controls.enableDamping = true;
            controls.dampingFactor = 0.05;

            // Lights
            const ambientLight = new THREE.AmbientLight(0x404040, 0.5);
            scene.add(ambientLight);

            const pointLight = new THREE.PointLight(0xffffff, 0.8);
            pointLight.position.set(5, 5, 5);
            scene.add(pointLight);

            // Groups for organization
            fieldGroup = new THREE.Group();
            scene.add(fieldGroup);

            sourcesGroup = new THREE.Group();
            scene.add(sourcesGroup);

            // Grid helper
            gridHelper = new THREE.GridHelper(2, 20, 0x4fc3f7, 0x2a5298);
            gridHelper.position.y = -1;
            scene.add(gridHelper);

            // Axes helper
            const axesHelper = new THREE.AxesHelper(1);
            scene.add(axesHelper);

            // Event listeners
            resolutionSlider.addEventListener('input', updateDisplayValues);
            gridSizeSlider.addEventListener('input', updateDisplayValues);
            vectorScaleSlider.addEventListener('input', () => {
                updateDisplayValues();
                if (currentFieldData) visualizeField(currentFieldData);
            });
            colorIntensitySlider.addEventListener('input', () => {
                updateDisplayValues();
                if (currentFieldData) visualizeField(currentFieldData);
            });
            displayModeSelect.addEventListener('change', () => {
                if (currentFieldData) visualizeField(currentFieldData);
            });
            showSourcesCheckbox.addEventListener('change', () => {
                sourcesGroup.visible = showSourcesCheckbox.checked;
            });
            showGridCheckbox.addEventListener('change', () => {
                gridHelper.visible = showGridCheckbox.checked;
            });
            calculateBtn.addEventListener('click', calculateField);
            resetViewBtn.addEventListener('click', resetView);
            exportJsonBtn.addEventListener('click', exportToJSON);
            exportVectorFieldBtn.addEventListener('click', exportToCSV);

            window.addEventListener('resize', onWindowResize);

            updateDisplayValues();
            animate();
        }

        function updateDisplayValues() {
            document.getElementById('resDisplay').textContent = resolutionSlider.value;
            document.getElementById('sizeDisplay').textContent = gridSizeSlider.value + 'm';
            document.getElementById('scaleDisplay').textContent = vectorScaleSlider.value;
            document.getElementById('colorDisplay').textContent = colorIntensitySlider.value;
        }

        function updateStatus(message, type = 'info') {
            statusDiv.textContent = message;
            statusDiv.className = type;
        }

        async function calculateField() {
            const resolution = parseInt(resolutionSlider.value);
            const size = parseFloat(gridSizeSlider.value);

            updateStatus('Calculating field...', 'loading');
            calculateBtn.disabled = true;

            try {
                const response = await fetch(`scripts/switch_fetch.php?action=calc_field_grid&` +
                    `resolution=${resolution}&` +
                    `xMin=${-size}&xMax=${size}&` +
                    `yMin=${-size}&yMax=${size}&` +
                    `zMin=${-size}&zMax=${size}`
                );

                const data = await response.json();

                if (data.error) {
                    throw new Error(data.error);
                }

                currentFieldData = data;
                visualizeField(data);

                updateStatus(`✓ Calculated ${data.totalPoints} field points`, 'info');
                document.getElementById('pointCount').textContent = data.totalPoints;
                document.getElementById('sourceCount').textContent = data.sources.length;

            } catch (error) {
                updateStatus(`✗ Error: ${error.message}`, 'error');
                console.error(error);
            } finally {
                calculateBtn.disabled = false;
            }
        }

        function visualizeField(data) {
            // Clear existing field visualization
            while (fieldGroup.children.length > 0) {
                fieldGroup.remove(fieldGroup.children[0]);
            }
            while (sourcesGroup.children.length > 0) {
                sourcesGroup.remove(sourcesGroup.children[0]);
            }

            const mode = displayModeSelect.value;
            const vectorScale = parseFloat(vectorScaleSlider.value);
            const colorIntensity = parseFloat(colorIntensitySlider.value);

            // Find max magnitude for color scaling
            let maxMagnitude = 0;
            data.grid.forEach(point => {
                if (point.magnitude > maxMagnitude) {
                    maxMagnitude = point.magnitude;
                }
            });

            document.getElementById('maxField').textContent = maxMagnitude.toExponential(2);

            // Visualize field based on mode
            if (mode === 'vectors' || mode === 'combined') {
                visualizeVectors(data.grid, maxMagnitude, vectorScale, colorIntensity);
            }

            if (mode === 'magnitude' || mode === 'combined') {
                visualizeMagnitudeCloud(data.grid, maxMagnitude, colorIntensity);
            }

            if (mode === 'streamlines') {
                visualizeStreamlines(data.grid, maxMagnitude, vectorScale, colorIntensity);
            }

            // Visualize sources
            visualizeSources(data.sources);
        }

        function visualizeVectors(grid, maxMagnitude, scale, colorIntensity) {
            grid.forEach(point => {
                const pos = point.position;
                const field = point.field;
                const mag = point.magnitude;

                if (mag < maxMagnitude * 1e-6) return; // Skip very weak fields

                // Normalize and scale vector
                const dir = new THREE.Vector3(field.x, field.y, field.z).normalize();
                const length = Math.log10(mag / maxMagnitude + 1) * scale * 0.3;

                // Color based on magnitude
                const t = Math.pow(mag / maxMagnitude, 1 / colorIntensity);
                const color = new THREE.Color().setHSL(0.6 - t * 0.6, 1, 0.5);

                // Arrow
                const arrowHelper = new THREE.ArrowHelper(
                    dir,
                    new THREE.Vector3(pos.x, pos.y, pos.z),
                    length,
                    color,
                    length * 0.2,
                    length * 0.1
                );
                fieldGroup.add(arrowHelper);
            });
        }

        function visualizeMagnitudeCloud(grid, maxMagnitude, colorIntensity) {
            const geometry = new THREE.BufferGeometry();
            const positions = [];
            const colors = [];
            const sizes = [];

            grid.forEach(point => {
                const pos = point.position;
                const mag = point.magnitude;

                if (mag < maxMagnitude * 1e-6) return;

                positions.push(pos.x, pos.y, pos.z);

                const t = Math.pow(mag / maxMagnitude, 1 / colorIntensity);
                const color = new THREE.Color().setHSL(0.6 - t * 0.6, 1, 0.5);
                colors.push(color.r, color.g, color.b);

                sizes.push(10 + t * 20);
            });

            geometry.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3));
            geometry.setAttribute('color', new THREE.Float32BufferAttribute(colors, 3));
            geometry.setAttribute('size', new THREE.Float32BufferAttribute(sizes, 1));

            const material = new THREE.PointsMaterial({
                size: 0.05,
                vertexColors: true,
                transparent: true,
                opacity: 0.6,
                sizeAttenuation: true
            });

            const points = new THREE.Points(geometry, material);
            fieldGroup.add(points);
        }

        function visualizeStreamlines(grid, maxMagnitude, scale, colorIntensity) {
            // Simple streamline visualization - trace field lines
            const resolution = Math.round(Math.cbrt(grid.length));
            const step = Math.max(1, Math.floor(resolution / 5));

            for (let i = 0; i < grid.length; i += step * step * step) {
                const startPoint = grid[i];
                if (!startPoint || startPoint.magnitude < maxMagnitude * 0.01) continue;

                const points = traceStreamline(grid, startPoint, maxMagnitude, 20);
                if (points.length < 2) continue;

                const geometry = new THREE.BufferGeometry().setFromPoints(points);
                const t = startPoint.magnitude / maxMagnitude;
                const color = new THREE.Color().setHSL(0.6 - t * 0.6, 1, 0.5);

                const material = new THREE.LineBasicMaterial({
                    color: color,
                    transparent: true,
                    opacity: 0.7
                });

                const line = new THREE.Line(geometry, material);
                fieldGroup.add(line);
            }
        }

        function traceStreamline(grid, startPoint, maxMagnitude, maxSteps) {
            const points = [];
            let currentPos = new THREE.Vector3(
                startPoint.position.x,
                startPoint.position.y,
                startPoint.position.z
            );
            points.push(currentPos.clone());

            const stepSize = 0.05;

            for (let i = 0; i < maxSteps; i++) {
                const field = interpolateField(grid, currentPos);
                if (!field || field.magnitude < maxMagnitude * 1e-6) break;

                const dir = new THREE.Vector3(field.x, field.y, field.z).normalize();
                currentPos.add(dir.multiplyScalar(stepSize));

                // Check bounds
                const bounds = 2;
                if (Math.abs(currentPos.x) > bounds ||
                    Math.abs(currentPos.y) > bounds ||
                    Math.abs(currentPos.z) > bounds) break;

                points.push(currentPos.clone());
            }

            return points;
        }

        function interpolateField(grid, position) {
            // Simple nearest neighbor interpolation
            let nearest = null;
            let minDist = Infinity;

            grid.forEach(point => {
                const dx = point.position.x - position.x;
                const dy = point.position.y - position.y;
                const dz = point.position.z - position.z;
                const dist = dx * dx + dy * dy + dz * dz;

                if (dist < minDist) {
                    minDist = dist;
                    nearest = point;
                }
            });

            return nearest ? nearest.field : null;
        }

        function visualizeSources(sources) {
            sources.forEach(source => {
                const radius = source.radius || 0.01;

                // Coil visualization
                const geometry = new THREE.TorusGeometry(radius, radius * 0.1, 16, 32);
                const material = new THREE.MeshPhongMaterial({
                    color: 0xffa500,
                    emissive: 0xff6600,
                    transparent: true,
                    opacity: 0.8
                });
                const torus = new THREE.Mesh(geometry, material);
                torus.position.set(0, source.position.alt || 0, 0);
                sourcesGroup.add(torus);

                // Current direction indicator
                const arrowGeometry = new THREE.ConeGeometry(radius * 0.3, radius * 0.5, 8);
                const arrowMaterial = new THREE.MeshBasicMaterial({ color: 0x00ff00 });
                const arrow = new THREE.Mesh(arrowGeometry, arrowMaterial);
                arrow.position.set(radius, source.position.alt || 0, 0);
                arrow.rotation.z = -Math.PI / 2;
                sourcesGroup.add(arrow);
            });
        }

        function resetView() {
            camera.position.set(2, 2, 2);
            camera.lookAt(0, 0, 0);
            controls.reset();
        }

        function exportToJSON() {
            if (!currentFieldData) {
                alert('Please calculate field first');
                return;
            }

            const exportData = {
                metadata: {
                    type: 'magnetic_field_3d',
                    software: 'ArtMag Electromagnetic Simulator',
                    timestamp: new Date().toISOString(),
                    resolution: currentFieldData.resolution,
                    bounds: currentFieldData.bounds
                },
                sources: currentFieldData.sources,
                field: currentFieldData.grid,
                maxMagnitude: parseFloat(document.getElementById('maxField').textContent)
            };

            const blob = new Blob([JSON.stringify(exportData, null, 2)], {
                type: 'application/json'
            });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `magnetic_field_${Date.now()}.json`;
            a.click();
            URL.revokeObjectURL(url);

            updateStatus('✓ Exported to JSON', 'info');
        }

        function exportToCSV() {
            if (!currentFieldData) {
                alert('Please calculate field first');
                return;
            }

            let csv = 'x,y,z,Bx,By,Bz,magnitude\n';
            currentFieldData.grid.forEach(point => {
                csv += `${point.position.x},${point.position.y},${point.position.z},` +
                       `${point.field.x},${point.field.y},${point.field.z},` +
                       `${point.magnitude}\n`;
            });

            const blob = new Blob([csv], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `magnetic_field_${Date.now()}.csv`;
            a.click();
            URL.revokeObjectURL(url);

            updateStatus('✓ Exported to CSV', 'info');
        }

        function onWindowResize() {
            const viewerDiv = document.getElementById('viewer');
            camera.aspect = viewerDiv.clientWidth / viewerDiv.clientHeight;
            camera.updateProjectionMatrix();
            renderer.setSize(viewerDiv.clientWidth, viewerDiv.clientHeight);
        }

        let lastTime = performance.now();
        let frameCount = 0;

        function animate() {
            animationId = requestAnimationFrame(animate);

            controls.update();
            renderer.render(scene, camera);

            // FPS counter
            frameCount++;
            const now = performance.now();
            if (now - lastTime > 1000) {
                document.getElementById('fps').textContent = frameCount;
                frameCount = 0;
                lastTime = now;
            }
        }

        // Initialize when page loads
        init();
    </script>
</body>
</html>
