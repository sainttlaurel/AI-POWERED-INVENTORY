<?php
require_once 'config/database.php';
require_once 'config/session.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $database = new Database();
        $db = $database->getConnection();

        if ($db) {
            $query = "SELECT id, username, password, role, status FROM users WHERE username = :username";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (isset($user['status']) && $user['status'] !== 'active') {
                    $error = 'Account is ' . $user['status'] . '. Please contact your administrator.';
                } else {
                    if (password_verify($password, $user['password'])) {
                        $_SESSION['user_id']  = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role']     = $user['role'];

                        try {
                            $update_stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                            $update_stmt->execute([$user['id']]);
                        } catch (Exception $e) {
                            error_log("Failed to update last login: " . $e->getMessage());
                        }

                        try {
                            logUserActivity('login', "Successful login from " . $_SERVER['REMOTE_ADDR'], $user['id']);
                        } catch (Exception $e) {
                            error_log("Failed to log activity: " . $e->getMessage());
                        }

                        header("Location: dashboard.php");
                        exit();
                    } else {
                        $error = 'Invalid username or password.';
                    }
                }
            } else {
                $error = 'Invalid username or password.';
            }
        } else {
            $error = 'Database connection failed. Please run setup first.';
        }
    } else {
        $error = 'Please enter both username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — InvenAI Inventory System</title>
    <meta name="description" content="AI-Powered Inventory Management System — Sign In">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg-base:      #0a0f1e;
            --bg-card:      #0f1629;
            --bg-elevated:  #131b2e;
            --accent:       #6366f1;
            --accent-cyan:  #06b6d4;
            --accent-violet:#8b5cf6;
            --text-primary: #f1f5f9;
            --text-secondary:#94a3b8;
            --text-muted:   #64748b;
            --border:       rgba(99,102,241,0.2);
            --grad-primary: linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%);
            --grad-cyan:    linear-gradient(135deg,#06b6d4 0%,#0ea5e9 100%);
            --ease-bounce:  cubic-bezier(0.34,1.56,0.64,1);
        }

        html, body { height: 100%; font-family: 'Inter', system-ui, sans-serif; -webkit-font-smoothing: antialiased; }

        body {
            background: var(--bg-base);
            color: var(--text-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            overflow: hidden;
            position: relative;
        }

        /* ── Three.js Canvas ── */
        #bg-canvas {
            position: fixed;
            inset: 0;
            z-index: 0;
            display: block;
        }

        /* ── Gradient Orbs ── */
        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            pointer-events: none;
            z-index: 0;
        }
        .orb-1 {
            width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(99,102,241,0.18) 0%, transparent 70%);
            top: -150px; left: -150px;
            animation: orbFloat1 12s ease-in-out infinite;
        }
        .orb-2 {
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(6,182,212,0.12) 0%, transparent 70%);
            bottom: -100px; right: -100px;
            animation: orbFloat2 14s ease-in-out infinite;
        }
        .orb-3 {
            width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(139,92,246,0.12) 0%, transparent 70%);
            top: 50%; left: 50%;
            transform: translate(-50%,-50%);
            animation: orbFloat3 10s ease-in-out infinite;
        }
        @keyframes orbFloat1 { 0%,100%{transform:translate(0,0)} 50%{transform:translate(40px,30px)} }
        @keyframes orbFloat2 { 0%,100%{transform:translate(0,0)} 50%{transform:translate(-30px,-40px)} }
        @keyframes orbFloat3 { 0%,100%{transform:translate(-50%,-50%) scale(1)} 50%{transform:translate(-50%,-50%) scale(1.2)} }

        /* ── Login Card ── */
        .login-wrapper {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 440px;
            padding: 1.5rem;
            animation: cardEnter 0.7s var(--ease-bounce) both;
        }
        @keyframes cardEnter {
            from { opacity: 0; transform: translateY(40px) scale(0.95); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        .login-card {
            background: rgba(15,22,41,0.85);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid var(--border);
            border-radius: 24px;
            box-shadow: 0 25px 80px rgba(0,0,0,0.6), 0 0 60px rgba(99,102,241,0.08), inset 0 1px 0 rgba(255,255,255,0.06);
            overflow: hidden;
        }

        /* Top accent bar */
        .card-accent-bar {
            height: 3px;
            background: linear-gradient(90deg, var(--accent) 0%, var(--accent-cyan) 50%, var(--accent-violet) 100%);
        }

        .card-inner { padding: 2.5rem 2rem 2rem; }

        /* Logo & Brand */
        .brand-section {
            text-align: center;
            margin-bottom: 2rem;
        }
        .brand-logo {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 72px; height: 72px;
            background: var(--grad-primary);
            border-radius: 20px;
            margin-bottom: 1rem;
            box-shadow: 0 8px 32px rgba(99,102,241,0.4);
            position: relative;
            animation: logoFloat 4s ease-in-out infinite;
        }
        @keyframes logoFloat {
            0%,100% { transform: translateY(0) rotate(0deg); box-shadow: 0 8px 32px rgba(99,102,241,0.4); }
            50%      { transform: translateY(-6px) rotate(2deg); box-shadow: 0 16px 40px rgba(99,102,241,0.6); }
        }
        .brand-logo i { font-size: 2rem; color: white; }
        .brand-logo::after {
            content: '';
            position: absolute;
            inset: -2px;
            border-radius: 22px;
            background: linear-gradient(135deg, rgba(99,102,241,0.6), rgba(6,182,212,0.3), transparent);
            z-index: -1;
            animation: logoBorder 3s linear infinite;
        }
        @keyframes logoBorder { to { filter: hue-rotate(360deg); } }

        .brand-title {
            font-size: 1.75rem;
            font-weight: 800;
            background: linear-gradient(135deg, #f1f5f9 0%, #94a3b8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.03em;
        }
        .brand-title span {
            background: var(--grad-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .brand-sub {
            font-size: 0.82rem;
            color: var(--text-muted);
            margin-top: 0.3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
        }
        .brand-sub i { color: var(--accent); font-size: 0.75rem; }

        /* Section title */
        .section-title {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
            text-align: center;
            position: relative;
        }
        .section-title::before, .section-title::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 28%;
            height: 1px;
            background: var(--border);
        }
        .section-title::before { left: 0; }
        .section-title::after  { right: 0; }

        /* Error alert */
        .error-alert {
            background: rgba(244,63,94,0.1);
            border: 1px solid rgba(244,63,94,0.3);
            border-radius: 12px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            color: #fb7185;
            font-size: 0.875rem;
            animation: shake 0.5s ease;
        }
        @keyframes shake {
            0%,100%{transform:translateX(0)} 20%{transform:translateX(-8px)}
            40%{transform:translateX(8px)} 60%{transform:translateX(-5px)}
            80%{transform:translateX(5px)}
        }

        /* Form */
        .form-group { margin-bottom: 1.1rem; }
        .form-label {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 0.45rem;
        }
        .form-label i { color: var(--accent); font-size: 0.85em; }
        .input-wrapper { position: relative; }
        .form-input {
            width: 100%;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(99,102,241,0.2);
            border-radius: 12px;
            padding: 0.75rem 1rem 0.75rem 2.75rem;
            color: var(--text-primary);
            font-size: 0.9rem;
            font-family: 'Inter', sans-serif;
            transition: all 0.25s cubic-bezier(0.4,0,0.2,1);
            outline: none;
        }
        .form-input:hover { border-color: rgba(99,102,241,0.4); }
        .form-input:focus {
            background: rgba(99,102,241,0.08);
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.15), 0 0 20px rgba(99,102,241,0.1);
        }
        .form-input::placeholder { color: var(--text-muted); }
        .input-icon {
            position: absolute;
            left: 0.9rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1rem;
            transition: color 0.2s;
            pointer-events: none;
        }
        .input-wrapper:focus-within .input-icon { color: var(--accent); }

        /* Password toggle */
        .password-wrapper { position: relative; }
        .password-toggle {
            position: absolute;
            right: 0.9rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 0.2rem;
            transition: color 0.2s;
            font-size: 1rem;
            display: flex;
            align-items: center;
        }
        .password-toggle:hover { color: var(--accent); }

        /* Remember me */
        .remember-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            font-size: 0.825rem;
        }
        .remember-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            cursor: pointer;
            user-select: none;
        }
        .remember-label input[type="checkbox"] {
            width: 16px; height: 16px;
            border: 1.5px solid rgba(99,102,241,0.4);
            border-radius: 4px;
            background: transparent;
            cursor: pointer;
            appearance: none;
            position: relative;
            transition: all 0.2s;
            flex-shrink: 0;
        }
        .remember-label input[type="checkbox"]:checked {
            background: var(--grad-primary);
            border-color: var(--accent);
        }
        .remember-label input[type="checkbox"]:checked::before {
            content: '✓';
            position: absolute;
            top: -1px; left: 2px;
            color: white;
            font-size: 11px;
            font-weight: 700;
        }
        .forgot-link { color: var(--accent); font-weight: 500; text-decoration: none; transition: color 0.2s; }
        .forgot-link:hover { color: var(--accent-cyan); }

        /* Submit button */
        .btn-login {
            width: 100%;
            background: var(--grad-primary);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 0.95rem;
            font-weight: 700;
            font-family: 'Inter', sans-serif;
            padding: 0.85rem 1rem;
            cursor: pointer;
            transition: all 0.3s var(--ease-bounce);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 8px 24px rgba(99,102,241,0.4);
            position: relative;
            overflow: hidden;
            letter-spacing: 0.01em;
        }
        .btn-login::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.15), transparent);
            opacity: 0;
            transition: opacity 0.3s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 32px rgba(99,102,241,0.6);
        }
        .btn-login:hover::before { opacity: 1; }
        .btn-login:active { transform: translateY(0); }
        .btn-login.loading { pointer-events: none; }
        .btn-login .btn-spinner {
            display: none;
            width: 18px; height: 18px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }
        .btn-login.loading .btn-spinner { display: block; }
        .btn-login.loading .btn-text { display: none; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Default credentials hint */
        .credentials-hint {
            margin-top: 1.25rem;
            padding: 0.7rem 1rem;
            background: rgba(6,182,212,0.06);
            border: 1px solid rgba(6,182,212,0.15);
            border-radius: 10px;
            text-align: center;
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        .credentials-hint strong { color: var(--accent-cyan); }

        /* Footer */
        .login-footer {
            text-align: center;
            padding: 1.25rem 2rem 1.5rem;
            font-size: 0.75rem;
            color: var(--text-muted);
            border-top: 1px solid rgba(99,102,241,0.08);
        }
        .login-footer a { color: var(--accent); }

        /* Features badges */
        .feature-badges {
            display: flex;
            justify-content: center;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-top: 0.5rem;
        }
        .feature-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.7rem;
            color: var(--text-muted);
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 20px;
            padding: 0.25rem 0.6rem;
        }
        .feature-badge i { font-size: 0.75rem; color: var(--accent); }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-thumb { background: rgba(99,102,241,0.4); border-radius: 2px; }
    </style>
</head>
<body>
    <!-- 3D Background Canvas -->
    <canvas id="bg-canvas"></canvas>

    <!-- Ambient orbs -->
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <!-- Login Card -->
    <div class="login-wrapper">
        <div class="login-card">
            <div class="card-accent-bar"></div>
            <div class="card-inner">

                <!-- Brand -->
                <div class="brand-section">
                    <div class="brand-logo">
                        <i class="bi bi-boxes"></i>
                    </div>
                    <div class="brand-title">Inven<span>AI</span></div>
                    <div class="brand-sub">
                        <i class="bi bi-cpu-fill"></i>
                        AI-Powered Inventory Management
                    </div>
                </div>

                <div class="section-title">Welcome Back</div>

                <!-- Error -->
                <?php if ($error): ?>
                <div class="error-alert">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
                <?php endif; ?>

                <!-- Form -->
                <form method="POST" id="loginForm" autocomplete="on">
                    <div class="form-group">
                        <label class="form-label" for="username">
                            <i class="bi bi-person-fill"></i> Username
                        </label>
                        <div class="input-wrapper">
                            <i class="bi bi-person input-icon"></i>
                            <input
                                type="text"
                                id="username"
                                name="username"
                                class="form-input"
                                placeholder="Enter your username"
                                required
                                autocomplete="username"
                                value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                            >
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password">
                            <i class="bi bi-lock-fill"></i> Password
                        </label>
                        <div class="input-wrapper password-wrapper">
                            <i class="bi bi-lock input-icon"></i>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-input"
                                placeholder="Enter your password"
                                required
                                autocomplete="current-password"
                                style="padding-right: 3rem;"
                            >
                            <button type="button" class="password-toggle" id="togglePassword" title="Show/hide password">
                                <i class="bi bi-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="remember-row">
                        <label class="remember-label">
                            <input type="checkbox" name="remember" id="remember">
                            Remember me
                        </label>
                        <a href="#" class="forgot-link">Forgot password?</a>
                    </div>

                    <button type="submit" class="btn-login" id="loginBtn">
                        <div class="btn-spinner"></div>
                        <span class="btn-text">
                            <i class="bi bi-box-arrow-in-right"></i>
                            Sign In
                        </span>
                    </button>
                </form>

                <div class="credentials-hint">
                    <i class="bi bi-info-circle"></i>
                    Default &nbsp; <strong>admin</strong> / <strong>admin123</strong>
                </div>
            </div>

            <div class="login-footer">
                <div class="feature-badges">
                    <span class="feature-badge"><i class="bi bi-shield-check"></i> Secure</span>
                    <span class="feature-badge"><i class="bi bi-graph-up-arrow"></i> AI Forecasting</span>
                    <span class="feature-badge"><i class="bi bi-bell"></i> Smart Alerts</span>
                    <span class="feature-badge"><i class="bi bi-qr-code"></i> QR Inventory</span>
                </div>
                <p style="margin-top:0.75rem;">&copy; <?php echo date('Y'); ?> InvenAI &mdash; All rights reserved</p>
            </div>
        </div>
    </div>

    <!-- Three.js -->
    <script src="https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.min.js"></script>
    <script>
    // ── 3D Particle Network ───────────────────────────────────────────────
    (function() {
        const canvas  = document.getElementById('bg-canvas');
        const scene   = new THREE.Scene();
        const camera  = new THREE.PerspectiveCamera(60, window.innerWidth / window.innerHeight, 0.1, 1000);
        const renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: true });
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        renderer.setSize(window.innerWidth, window.innerHeight);
        renderer.setClearColor(0x000000, 0);

        camera.position.z = 80;

        // Particles
        const PARTICLE_COUNT = 200;
        const positions = new Float32Array(PARTICLE_COUNT * 3);
        const colors    = new Float32Array(PARTICLE_COUNT * 3);
        const speeds    = [];
        const palettes  = [
            [0.39, 0.40, 0.95],  // indigo
            [0.024, 0.71, 0.83], // cyan
            [0.54, 0.36, 0.96],  // violet
        ];

        for (let i = 0; i < PARTICLE_COUNT; i++) {
            positions[i*3]   = (Math.random()-0.5) * 160;
            positions[i*3+1] = (Math.random()-0.5) * 120;
            positions[i*3+2] = (Math.random()-0.5) * 80;
            speeds.push({
                x: (Math.random()-0.5) * 0.04,
                y: (Math.random()-0.5) * 0.04,
                z: (Math.random()-0.5) * 0.02,
            });
            const c = palettes[Math.floor(Math.random() * palettes.length)];
            colors[i*3]=c[0]; colors[i*3+1]=c[1]; colors[i*3+2]=c[2];
        }

        const geo = new THREE.BufferGeometry();
        geo.setAttribute('position', new THREE.BufferAttribute(positions, 3));
        geo.setAttribute('color',    new THREE.BufferAttribute(colors,    3));
        const mat = new THREE.PointsMaterial({
            size: 1.2,
            vertexColors: true,
            transparent: true,
            opacity: 0.7,
            sizeAttenuation: true,
        });
        const particles = new THREE.Points(geo, mat);
        scene.add(particles);

        // Lines connecting nearby particles
        const lineMat = new THREE.LineBasicMaterial({ color: 0x6366f1, transparent: true, opacity: 0.08 });
        const lineGeo = new THREE.BufferGeometry();
        const linePositions = new Float32Array(PARTICLE_COUNT * PARTICLE_COUNT * 6);
        lineGeo.setAttribute('position', new THREE.BufferAttribute(linePositions, 3));
        const lines = new THREE.LineSegments(lineGeo, lineMat);
        scene.add(lines);

        // Mouse parallax
        let mouseX = 0, mouseY = 0;
        document.addEventListener('mousemove', e => {
            mouseX = (e.clientX / window.innerWidth  - 0.5) * 0.5;
            mouseY = (e.clientY / window.innerHeight - 0.5) * 0.5;
        });

        // Resize
        window.addEventListener('resize', () => {
            camera.aspect = window.innerWidth / window.innerHeight;
            camera.updateProjectionMatrix();
            renderer.setSize(window.innerWidth, window.innerHeight);
        });

        // Animate
        let lineIdx = 0;
        const CONNECT_DIST = 30;
        let frame = 0;

        function animate() {
            requestAnimationFrame(animate);
            frame++;

            // Move particles
            const pos = geo.attributes.position.array;
            for (let i = 0; i < PARTICLE_COUNT; i++) {
                pos[i*3]   += speeds[i].x;
                pos[i*3+1] += speeds[i].y;
                pos[i*3+2] += speeds[i].z;
                if (Math.abs(pos[i*3])   > 80)  speeds[i].x *= -1;
                if (Math.abs(pos[i*3+1]) > 60)  speeds[i].y *= -1;
                if (Math.abs(pos[i*3+2]) > 40)  speeds[i].z *= -1;
            }
            geo.attributes.position.needsUpdate = true;

            // Update lines every 3 frames for performance
            if (frame % 3 === 0) {
                lineIdx = 0;
                for (let i = 0; i < PARTICLE_COUNT; i++) {
                    for (let j = i+1; j < PARTICLE_COUNT; j++) {
                        const dx = pos[i*3]   - pos[j*3];
                        const dy = pos[i*3+1] - pos[j*3+1];
                        const dz = pos[i*3+2] - pos[j*3+2];
                        const d  = Math.sqrt(dx*dx + dy*dy + dz*dz);
                        if (d < CONNECT_DIST) {
                            linePositions[lineIdx++] = pos[i*3];
                            linePositions[lineIdx++] = pos[i*3+1];
                            linePositions[lineIdx++] = pos[i*3+2];
                            linePositions[lineIdx++] = pos[j*3];
                            linePositions[lineIdx++] = pos[j*3+1];
                            linePositions[lineIdx++] = pos[j*3+2];
                        }
                    }
                }
                lineGeo.attributes.position.needsUpdate = true;
                lineGeo.setDrawRange(0, lineIdx / 3);
            }

            // Camera parallax
            camera.position.x += (mouseX * 20 - camera.position.x) * 0.04;
            camera.position.y += (-mouseY * 20 - camera.position.y) * 0.04;
            camera.lookAt(scene.position);

            // Gentle rotation
            particles.rotation.y += 0.0005;

            renderer.render(scene, camera);
        }
        animate();
    })();

    // ── Password toggle ───────────────────────────────────────────────────
    document.getElementById('togglePassword').addEventListener('click', function() {
        const pwd  = document.getElementById('password');
        const icon = document.getElementById('toggleIcon');
        if (pwd.type === 'password') {
            pwd.type = 'text';
            icon.className = 'bi bi-eye-slash';
        } else {
            pwd.type = 'password';
            icon.className = 'bi bi-eye';
        }
    });

    // ── Loading state on submit ───────────────────────────────────────────
    document.getElementById('loginForm').addEventListener('submit', function() {
        const btn = document.getElementById('loginBtn');
        btn.classList.add('loading');
    });

    // ── Input focus effects ───────────────────────────────────────────────
    document.querySelectorAll('.form-input').forEach(input => {
        input.addEventListener('focus', function() {
            this.closest('.input-wrapper').style.transform = 'scale(1.01)';
        });
        input.addEventListener('blur', function() {
            this.closest('.input-wrapper').style.transform = '';
        });
    });
    </script>
</body>
</html>
