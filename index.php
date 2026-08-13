<?php
    session_start();
    require_once 'db/db_con.php'; 

    function getRedirectUrl($targetPage) {
        if (!isset($_SESSION['user_id'])) {
            return 'pages/login.php';
        }
        return $targetPage;
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RoadRanger | Smart Road Safety Education</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f8f9fa;
            color: #333;
        }
        header {
            background: #2c3e50;
            color: white;
            padding: 60px 20px;
            text-align: center;
        }
        .container {
            max-width: 1000px;
            margin: 40px auto;
            text-align: center;
            padding: 20px;
        }
        .hero-title { font-size: 3rem; margin-bottom: 10px; }
        .hero-subtitle { font-size: 1.2rem; color: #bdc3c7; margin-bottom: 30px; }
        
        .game-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 40px;
        }
        .card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .card:hover { transform: translateY(-5px); }
        
        .btn {
            display: inline-block;
            padding: 12px 25px;
            background-color: #e67e22;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin-top: 15px;
            transition: background 0.3s;
        }
        .btn:hover { background-color: #d35400; }
        
        .login-status {
            position: absolute;
            top: 20px;
            right: 20px;
        }
        .login-status a { color: white; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>

    <header>
        <div class="login-status">
            <?php if(isset($_SESSION['user_id'])): ?>
                <span>Welcome, <?php echo $_SESSION['username']; ?> | <a href="pages/users/logout.php">Logout</a></span>
            <?php else: ?>
                <a href="pages/login.php">Login / Sign Up</a>
            <?php endif; ?>
        </div>
        <h1 class="hero-title">RoadRanger</h1>
        <p class="hero-subtitle">Official Road Safety Platform for San Ildefonso Traffic Management Unit</p>
    </header>

    <div class="container">
        <h2>Interactive Learning Modules</h2>
        <p>Choose a module to begin your traffic safety certification.</p>

        <div class="game-cards">
            <div class="card">
                <h3>Plate Recall</h3>
                <p>Train your eyes to identify and remember license plate details quickly.</p>
                <a href="<?php echo getRedirectUrl('pages/games/memory.php'); ?>" class="btn">Play Now</a>
            </div>

            <div class="card">
                <h3>Conveyor Mania</h3>
                <p>Sort traffic signs into the correct LTO categories in real-time.</p>
                <a href="<?php echo getRedirectUrl('pages/games/conveyor.php'); ?>" class="btn">Start Sorting</a>
            </div>

            <div class="card">
                <h3>Study Hall</h3>
                <p>Review the official LTO modules and traffic law documentation.</p>
                <a href="<?php echo getRedirectUrl('pages/users/Learnpage.php'); ?>" class="btn">View Lessons</a>
            </div>
        </div>
    </div>

</body>
</html>