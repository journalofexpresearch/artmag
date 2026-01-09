"""
Blender Magnetic Field Importer
================================
This script imports magnetic field data from the ArtMag simulator into Blender
and visualizes it as arrows, particles, or curves showing field lines.

Usage:
1. Export field data from 3dfieldview.php (JSON or CSV format)
2. Open Blender
3. Go to Scripting workspace
4. Open this file or paste the code
5. Update FILE_PATH variable below
6. Run script (Alt+P)

Visualization modes:
- 'arrows': Vector field arrows (good for understanding field direction)
- 'particles': Particle system following field lines (good for flow visualization)
- 'curves': Field line curves (good for emergent patterns)
"""

import bpy
import json
import csv
import math
from mathutils import Vector

# ===== CONFIGURATION =====
# Update this path to your exported file
FILE_PATH = "/path/to/magnetic_field_export.json"  # or .csv
VISUALIZATION_MODE = 'arrows'  # 'arrows', 'particles', or 'curves'

# Visual settings
ARROW_SCALE = 1.0
ARROW_COLOR_MODE = 'magnitude'  # 'magnitude' or 'direction'
SHOW_SOURCES = True
PARTICLE_COUNT = 1000
CURVE_STEPS = 50

# ===== IMPLEMENTATION =====

def clear_scene():
    """Remove all existing mesh objects from the scene"""
    bpy.ops.object.select_all(action='SELECT')
    bpy.ops.object.delete(use_global=False)

def load_field_data_json(filepath):
    """Load magnetic field data from JSON export"""
    with open(filepath, 'r') as f:
        data = json.load(f)

    field_points = []
    for point in data['field']:
        field_points.append({
            'position': Vector((
                point['position']['x'],
                point['position']['y'],
                point['position']['z']
            )),
            'field': Vector((
                point['field']['x'],
                point['field']['y'],
                point['field']['z']
            )),
            'magnitude': point['magnitude']
        })

    sources = []
    if 'sources' in data:
        for source in data['sources']:
            sources.append({
                'position': Vector((0, 0, source['position']['alt'])),
                'radius': source.get('radius', 0.01),
                'current': source.get('current', 0)
            })

    return field_points, sources, data.get('metadata', {})

def load_field_data_csv(filepath):
    """Load magnetic field data from CSV export"""
    field_points = []
    with open(filepath, 'r') as f:
        reader = csv.DictReader(f)
        for row in reader:
            x, y, z = float(row['x']), float(row['y']), float(row['z'])
            Bx, By, Bz = float(row['Bx']), float(row['By']), float(row['Bz'])
            mag = float(row['magnitude'])

            field_points.append({
                'position': Vector((x, y, z)),
                'field': Vector((Bx, By, Bz)),
                'magnitude': mag
            })

    return field_points, [], {}

def magnitude_to_color(magnitude, max_magnitude):
    """Convert field magnitude to color (blue=low, red=high)"""
    if max_magnitude == 0:
        return (0.5, 0.5, 0.5, 1.0)

    t = min(1.0, magnitude / max_magnitude)
    # HSV to RGB conversion for blue (240°) to red (0°)
    hue = (1.0 - t) * 0.666  # 0.666 = 240/360 (blue)

    import colorsys
    rgb = colorsys.hsv_to_rgb(hue, 1.0, 1.0)
    return (*rgb, 1.0)

def direction_to_color(field_vector):
    """Convert field direction to color"""
    norm = field_vector.normalized()
    # Map -1..1 to 0..1
    r = (norm.x + 1) / 2
    g = (norm.y + 1) / 2
    b = (norm.z + 1) / 2
    return (r, g, b, 1.0)

def create_arrow_mesh(name, position, direction, length, color):
    """Create an arrow mesh at the specified position"""
    # Create arrow from cone and cylinder
    bpy.ops.mesh.primitive_cone_add(
        radius1=0.02 * length,
        radius2=0,
        depth=0.1 * length,
        location=position + direction.normalized() * (length * 0.95)
    )
    cone = bpy.context.active_object
    cone.name = f"{name}_cone"

    bpy.ops.mesh.primitive_cylinder_add(
        radius=0.01 * length,
        depth=length * 0.9,
        location=position + direction.normalized() * (length * 0.45)
    )
    cylinder = bpy.context.active_object
    cylinder.name = f"{name}_cylinder"

    # Align to field direction
    rotation = direction.to_track_quat('Z', 'Y')
    cone.rotation_euler = rotation.to_euler()
    cylinder.rotation_euler = rotation.to_euler()

    # Join into single object
    bpy.ops.object.select_all(action='DESELECT')
    cone.select_set(True)
    cylinder.select_set(True)
    bpy.context.view_layer.objects.active = cone
    bpy.ops.object.join()

    arrow = bpy.context.active_object
    arrow.name = name

    # Apply color material
    mat = bpy.data.materials.new(name=f"{name}_material")
    mat.use_nodes = True
    bsdf = mat.node_tree.nodes["Principled BSDF"]
    bsdf.inputs['Base Color'].default_value = color
    bsdf.inputs['Emission'].default_value = color
    bsdf.inputs['Emission Strength'].default_value = 0.2
    arrow.data.materials.append(mat)

    return arrow

def visualize_arrows(field_points, max_magnitude):
    """Create arrow visualization of vector field"""
    print(f"Creating {len(field_points)} arrows...")

    collection = bpy.data.collections.new("Magnetic Field Arrows")
    bpy.context.scene.collection.children.link(collection)

    for i, point in enumerate(field_points):
        if i % 100 == 0:
            print(f"  Progress: {i}/{len(field_points)}")

        mag = point['magnitude']
        if mag < max_magnitude * 1e-6:
            continue

        # Scale arrow length logarithmically
        length = math.log10(mag / max_magnitude + 1) * ARROW_SCALE * 0.3

        # Color based on mode
        if ARROW_COLOR_MODE == 'magnitude':
            color = magnitude_to_color(mag, max_magnitude)
        else:
            color = direction_to_color(point['field'])

        arrow = create_arrow_mesh(
            f"arrow_{i}",
            point['position'],
            point['field'].normalized(),
            length,
            color
        )

        # Move to collection
        for coll in arrow.users_collection:
            coll.objects.unlink(arrow)
        collection.objects.link(arrow)

    print("Arrows created!")

def visualize_curves(field_points, max_magnitude):
    """Create curve visualization following field lines"""
    print(f"Creating streamlines from {len(field_points)} points...")

    collection = bpy.data.collections.new("Magnetic Field Lines")
    bpy.context.scene.collection.children.link(collection)

    # Sample starting points
    step = max(1, len(field_points) // 50)
    streamline_count = 0

    for i in range(0, len(field_points), step):
        start_point = field_points[i]
        if start_point['magnitude'] < max_magnitude * 0.01:
            continue

        # Trace streamline
        points = trace_field_line(field_points, start_point, max_magnitude, CURVE_STEPS)
        if len(points) < 2:
            continue

        # Create curve
        curve_data = bpy.data.curves.new(f'streamline_{streamline_count}', 'CURVE')
        curve_data.dimensions = '3D'
        curve_data.bevel_depth = 0.005
        curve_data.bevel_resolution = 2

        polyline = curve_data.splines.new('POLY')
        polyline.points.add(len(points) - 1)

        for j, point in enumerate(points):
            polyline.points[j].co = (*point['position'], 1)

        # Create object
        curve_obj = bpy.data.objects.new(f'streamline_{streamline_count}', curve_data)

        # Color based on start magnitude
        t = start_point['magnitude'] / max_magnitude
        color = magnitude_to_color(start_point['magnitude'], max_magnitude)

        mat = bpy.data.materials.new(name=f"streamline_{streamline_count}_mat")
        mat.use_nodes = True
        bsdf = mat.node_tree.nodes["Principled BSDF"]
        bsdf.inputs['Base Color'].default_value = color
        bsdf.inputs['Emission'].default_value = color
        bsdf.inputs['Emission Strength'].default_value = 0.5
        curve_obj.data.materials.append(mat)

        collection.objects.link(curve_obj)
        streamline_count += 1

    print(f"Created {streamline_count} streamlines!")

def trace_field_line(field_points, start_point, max_magnitude, max_steps):
    """Trace a field line from a starting point"""
    points = [start_point]
    current_pos = start_point['position'].copy()
    step_size = 0.05

    for _ in range(max_steps):
        # Interpolate field at current position
        field = interpolate_field(field_points, current_pos)
        if not field or field['magnitude'] < max_magnitude * 1e-6:
            break

        # Step along field direction
        direction = field['field'].normalized()
        current_pos += direction * step_size

        # Check bounds
        if abs(current_pos.x) > 2 or abs(current_pos.y) > 2 or abs(current_pos.z) > 2:
            break

        points.append({'position': current_pos.copy(), 'magnitude': field['magnitude']})

    return points

def interpolate_field(field_points, position):
    """Find nearest field point (simple interpolation)"""
    nearest = None
    min_dist = float('inf')

    for point in field_points:
        dist = (point['position'] - position).length_squared
        if dist < min_dist:
            min_dist = dist
            nearest = point

    return nearest

def create_sources(sources):
    """Visualize magnetic field sources (coils)"""
    if not sources or not SHOW_SOURCES:
        return

    print(f"Creating {len(sources)} source visualizations...")

    collection = bpy.data.collections.new("Field Sources")
    bpy.context.scene.collection.children.link(collection)

    for i, source in enumerate(sources):
        # Create torus for coil
        bpy.ops.mesh.primitive_torus_add(
            major_radius=source['radius'],
            minor_radius=source['radius'] * 0.1,
            location=source['position']
        )
        torus = bpy.context.active_object
        torus.name = f"source_{i}"

        # Orange emissive material
        mat = bpy.data.materials.new(name=f"source_{i}_mat")
        mat.use_nodes = True
        bsdf = mat.node_tree.nodes["Principled BSDF"]
        bsdf.inputs['Base Color'].default_value = (1.0, 0.6, 0.2, 1.0)
        bsdf.inputs['Emission'].default_value = (1.0, 0.4, 0.0, 1.0)
        bsdf.inputs['Emission Strength'].default_value = 2.0
        torus.data.materials.append(mat)

        # Move to collection
        for coll in torus.users_collection:
            coll.objects.unlink(torus)
        collection.objects.link(torus)

def setup_scene():
    """Configure scene for best visualization"""
    # Set background to dark
    bpy.context.scene.world.use_nodes = True
    bg = bpy.context.scene.world.node_tree.nodes['Background']
    bg.inputs[0].default_value = (0.02, 0.02, 0.05, 1.0)

    # Add camera
    bpy.ops.object.camera_add(location=(3, -3, 2))
    camera = bpy.context.active_object
    camera.rotation_euler = (1.1, 0, 0.785)
    bpy.context.scene.camera = camera

    # Add lights
    bpy.ops.object.light_add(type='SUN', location=(5, 5, 5))
    sun = bpy.context.active_object
    sun.data.energy = 1.0

def main():
    """Main function"""
    print("=" * 50)
    print("Blender Magnetic Field Importer")
    print("=" * 50)

    # Clear scene
    clear_scene()

    # Load data
    print(f"\nLoading data from: {FILE_PATH}")
    if FILE_PATH.endswith('.json'):
        field_points, sources, metadata = load_field_data_json(FILE_PATH)
    elif FILE_PATH.endswith('.csv'):
        field_points, sources, metadata = load_field_data_csv(FILE_PATH)
    else:
        print("Error: File must be .json or .csv")
        return

    print(f"Loaded {len(field_points)} field points")
    print(f"Loaded {len(sources)} sources")

    # Find max magnitude
    max_magnitude = max(p['magnitude'] for p in field_points)
    print(f"Max field magnitude: {max_magnitude:.6e} T")

    # Create visualization
    if VISUALIZATION_MODE == 'arrows':
        visualize_arrows(field_points, max_magnitude)
    elif VISUALIZATION_MODE == 'curves':
        visualize_curves(field_points, max_magnitude)
    else:
        print(f"Unknown visualization mode: {VISUALIZATION_MODE}")
        return

    # Create sources
    create_sources(sources)

    # Setup scene
    setup_scene()

    print("\n" + "=" * 50)
    print("Import complete!")
    print("=" * 50)
    print(f"\nVisualization mode: {VISUALIZATION_MODE}")
    print("Tip: Press 'Z' and select 'Rendered' for best view")
    print("Tip: Try switching to 'Shading' workspace for emergent pattern analysis")

# Run the script
if __name__ == "__main__":
    main()
