/**
 * Particle halos — scoped Three.js particle fields that wrap host elements
 * (hero planet on the homepage, sticky music player everywhere). Replaces the
 * previous full-viewport background, which read as overstimulating on every
 * page. Each halo is sized to its host with a small inset bleed so the
 * particles appear to glow around the element.
 *
 * Audio-reactive hooks (window.toggleBackgroundAudioVisualizer / FMLAudioData)
 * are preserved — when audio is playing, every halo pulses in sync.
 */

(function () {
    const halos = [];
    let audioVisualizerEnabled = false;
    const PARTICLE_SIZE = 12;
    const SPRITE_URL = 'https://threejs.org/examples/textures/sprites/disc.png';
    let sharedTexture = null;

    function getTexture() {
        if (!sharedTexture) {
            sharedTexture = new THREE.TextureLoader().load(SPRITE_URL);
        }
        return sharedTexture;
    }

    function createParticleHalo(host, opts) {
        opts = opts || {};
        const radius = opts.radius || 80;
        const segments = opts.segments || 24;
        const inset = (typeof opts.inset === 'number') ? opts.inset : -60;

        if (getComputedStyle(host).position === 'static') {
            host.style.position = 'relative';
        }

        const container = document.createElement('div');
        container.className = 'particle-halo';
        Object.assign(container.style, {
            position: 'absolute',
            top: inset + 'px',
            left: inset + 'px',
            right: inset + 'px',
            bottom: inset + 'px',
            zIndex: '0',
            pointerEvents: 'none',
            overflow: 'visible'
        });
        host.appendChild(container);

        const scene = new THREE.Scene();
        const rect0 = container.getBoundingClientRect();
        const w0 = Math.max(rect0.width, 120);
        const h0 = Math.max(rect0.height, 120);
        const camera = new THREE.PerspectiveCamera(45, w0 / h0, 1, 10000);
        camera.position.z = radius * 1.8;

        const sphereGeo = new THREE.SphereGeometry(radius, segments, segments);
        sphereGeo.deleteAttribute('normal');
        sphereGeo.deleteAttribute('uv');
        const posAttr = sphereGeo.getAttribute('position');

        const colors = [];
        const sizes = [];
        const tmp = new THREE.Color();
        for (let i = 0; i < posAttr.count; i++) {
            tmp.setHSL(0.01 + 0.1 * (i / posAttr.count), 1.0, 0.5);
            tmp.toArray(colors, i * 3);
            sizes[i] = PARTICLE_SIZE * 0.5;
        }
        const baseSizes = sizes.slice();
        const basePositions = Array.from(posAttr.array);

        const geo = new THREE.BufferGeometry();
        geo.setAttribute('position', posAttr.clone());
        geo.setAttribute('customColor', new THREE.Float32BufferAttribute(colors, 3));
        geo.setAttribute('size', new THREE.Float32BufferAttribute(sizes, 1));

        const material = new THREE.ShaderMaterial({
            uniforms: {
                color: { value: new THREE.Color(0xffffff) },
                pointTexture: { value: getTexture() },
                alphaTest: { value: 0.9 }
            },
            vertexShader:
                'attribute float size;' +
                'attribute vec3 customColor;' +
                'varying vec3 vColor;' +
                'void main() {' +
                '  vColor = customColor;' +
                '  vec4 mvPosition = modelViewMatrix * vec4(position, 1.0);' +
                '  gl_PointSize = size * (300.0 / -mvPosition.z);' +
                '  gl_Position = projectionMatrix * mvPosition;' +
                '}',
            fragmentShader:
                'uniform vec3 color;' +
                'uniform sampler2D pointTexture;' +
                'uniform float alphaTest;' +
                'varying vec3 vColor;' +
                'void main() {' +
                '  gl_FragColor = vec4(color * vColor, 1.0);' +
                '  gl_FragColor = gl_FragColor * texture2D(pointTexture, gl_PointCoord);' +
                '  if (gl_FragColor.a < alphaTest) discard;' +
                '}',
            blending: THREE.AdditiveBlending,
            depthTest: false,
            transparent: true,
            depthWrite: false
        });

        const particles = new THREE.Points(geo, material);
        scene.add(particles);

        const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
        renderer.setClearColor(0x000000, 0);
        renderer.setPixelRatio(window.devicePixelRatio);
        renderer.setSize(w0, h0);
        renderer.domElement.style.display = 'block';
        renderer.domElement.style.pointerEvents = 'none';
        container.appendChild(renderer.domElement);

        function resize() {
            const r = container.getBoundingClientRect();
            const w = Math.max(r.width, 120);
            const h = Math.max(r.height, 120);
            camera.aspect = w / h;
            camera.updateProjectionMatrix();
            renderer.setSize(w, h);
        }
        const ro = ('ResizeObserver' in window) ? new ResizeObserver(resize) : null;
        if (ro) ro.observe(container);
        window.addEventListener('resize', resize);

        const halo = {
            host: host,
            container: container,
            scene: scene,
            camera: camera,
            renderer: renderer,
            particles: particles,
            geo: geo,
            baseSizes: baseSizes,
            basePositions: basePositions,
            dispose: function () {
                if (ro) ro.disconnect();
                window.removeEventListener('resize', resize);
                renderer.dispose();
                container.remove();
            }
        };
        halos.push(halo);
        return halo;
    }

    function animate() {
        requestAnimationFrame(animate);
        const time = Date.now() * 0.001;
        const audio = (audioVisualizerEnabled && window.FMLAudioData) || null;
        const bass = audio ? (audio.bass || 0) : 0;
        const mid = audio ? (audio.mid || 0) : 0;
        const treble = audio ? (audio.treble || 0) : 0;
        const intensity = audio ? (audio.intensity || 0) : 0;

        for (let hi = 0; hi < halos.length; hi++) {
            const h = halos[hi];
            h.particles.rotation.x += 0.0005;
            h.particles.rotation.y += 0.001;

            const sizes = h.geo.attributes.size.array;
            const positions = h.geo.attributes.position.array;

            if (audio) {
                for (let i = 0, l = sizes.length; i < l; i++) {
                    const band = i % 3;
                    let pulse;
                    if (band === 0) pulse = 1 + bass * 1.5;
                    else if (band === 1) pulse = 1 + mid * 1.0;
                    else pulse = 1 + treble * 0.8;
                    const wave = 1 + Math.sin(time * 0.8 + i * 0.05) * intensity * 0.2;
                    sizes[i] = h.baseSizes[i] * pulse * wave;

                    const bx = h.basePositions[i * 3];
                    const by = h.basePositions[i * 3 + 1];
                    const bz = h.basePositions[i * 3 + 2];
                    if (bx || by || bz) {
                        const breathe = 1 + bass * 0.08 + Math.sin(time * 0.6 + i * 0.03) * intensity * 0.05;
                        positions[i * 3]     = bx * breathe;
                        positions[i * 3 + 1] = by * breathe;
                        positions[i * 3 + 2] = bz * breathe;
                    }
                }
                h.geo.attributes.size.needsUpdate = true;
                h.geo.attributes.position.needsUpdate = true;

                const colors = h.geo.attributes.customColor.array;
                const tc = new THREE.Color();
                for (let i = 0, l = colors.length / 3; i < l; i++) {
                    const t = i / l;
                    const hueShift = bass * 0.03 - treble * 0.05;
                    let hue = (0.01 + 0.1 * t + hueShift + Math.sin(time * 0.15) * 0.02) % 1;
                    if (hue < 0) hue += 1;
                    const sat = 0.8 + intensity * 0.2;
                    let light = 0.35 + intensity * 0.2 + Math.sin(time * 0.8 + i * 0.04) * bass * 0.08;
                    if (light > 0.7) light = 0.7;
                    tc.setHSL(hue, sat, light);
                    colors[i * 3]     = tc.r;
                    colors[i * 3 + 1] = tc.g;
                    colors[i * 3 + 2] = tc.b;
                }
                h.geo.attributes.customColor.needsUpdate = true;
            } else {
                let szu = false, pou = false;
                for (let i = 0, l = sizes.length; i < l; i++) {
                    if (sizes[i] !== h.baseSizes[i]) { sizes[i] = h.baseSizes[i]; szu = true; }
                }
                for (let i = 0, l = h.basePositions.length; i < l; i++) {
                    if (positions[i] !== h.basePositions[i]) { positions[i] = h.basePositions[i]; pou = true; }
                }
                if (szu) h.geo.attributes.size.needsUpdate = true;
                if (pou) h.geo.attributes.position.needsUpdate = true;
            }

            h.renderer.render(h.scene, h.camera);
        }
    }

    function init() {
        if (typeof THREE === 'undefined') {
            console.warn('[ParticleHalos] THREE not loaded — skipping');
            return;
        }

        const targets = [];
        document.querySelectorAll('.sh-planet-wrap').forEach(function (el) {
            targets.push({ el: el, radius: 110, inset: -80 });
        });

        if (!targets.length) return;
        targets.forEach(function (t) { createParticleHalo(t.el, { radius: t.radius, inset: t.inset }); });
        animate();
    }

    window.toggleBackgroundAudioVisualizer = function (enabled) {
        audioVisualizerEnabled = !!enabled;
        if (!enabled) {
            halos.forEach(function (h) {
                const sizes = h.geo.attributes.size.array;
                const positions = h.geo.attributes.position.array;
                for (let i = 0; i < sizes.length; i++) sizes[i] = h.baseSizes[i];
                for (let i = 0; i < h.basePositions.length; i++) positions[i] = h.basePositions[i];
                h.geo.attributes.size.needsUpdate = true;
                h.geo.attributes.position.needsUpdate = true;
            });
        }
    };
    window.getBackgroundVisualizerState = function () { return audioVisualizerEnabled; };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
