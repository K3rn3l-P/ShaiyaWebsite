<header class="header">
    <div class="logo-area">
        <div class="logo">Shaiya <span>Duff</span></div>
        <div class="slogan">The Ultimate Classic Experience</div>
    </div>

    <!-- Header Stats Bar -->
    <?php include_once 'stats_logic.php'; ?>
    <div class="header-stats">
        <div class="h-stats-item aol">
            <img src="assets/aol.webp" alt="AOL">
            <span><?php echo $aol_pct; ?>%</span>
        </div>
        <div class="h-stats-divider"></div>
        <div class="h-stats-item uof">
            <img src="assets/uof.webp" alt="UOF">
            <span><?php echo $uof_pct; ?>%</span>
        </div>
        <div class="h-stats-divider"></div>
        <div class="h-stats-online">
            <span><?php echo number_format((int) $online_count); ?></span> <?php echo ($online_count == 1) ? 'Player' : 'Players'; ?> Online
        </div>
        <div class="h-stats-divider"></div>
        <?php if (!empty($maintenance)): ?>
        <div class="h-stats-online h-stats-maintenance" title="Server under maintenance — normal player login is temporarily disabled">
            <span class="mnt-dot"></span> Maintenance
        </div>
        <?php else: ?>
        <div class="h-stats-online h-stats-server" title="<?php echo (!empty($server_online)) ? 'Server online' : 'Server offline'; ?>">
            Server <span class="srv-dot <?php echo (!empty($server_online)) ? 'srv-on' : 'srv-off'; ?>"></span>
        </div>
        <?php endif; ?>

    </div>

    <style>
        .h-stats-server { gap: 8px; }
        .h-stats-server .srv-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }
        .h-stats-server .srv-on {
            background: #5BD17B;
            box-shadow: 0 0 8px rgba(91, 209, 123, 0.7);
        }
        .h-stats-server .srv-off {
            background: #E0574B;
            box-shadow: 0 0 8px rgba(224, 87, 75, 0.7);
        }
        /* Maintenance indicator: replaces the Server dot in the stats bar while
           PS_UserData.dbo.MaintenanceMode.Enabled=1 (set from ShaiyaServerHub). Same gold accent
           as the rest of the site (--text-gold), pulsing to draw the eye without a full banner. */
        .h-stats-maintenance {
            gap: 8px;
            color: var(--text-gold);
            font-weight: 600;
        }
        .h-stats-maintenance .mnt-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            background: var(--text-gold);
            box-shadow: 0 0 8px rgba(232, 200, 129, 0.8);
            animation: mnt-pulse 1.4s ease-in-out infinite;
        }
        @keyframes mnt-pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.45; }
        }

        .header-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 220px;
            height: 42px;
            background: rgba(232, 200, 129, 0.1);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            border: 1px solid var(--text-gold);
            color: #fff;
            font-family: var(--heading-font);
            font-size: 14px;
            text-transform: uppercase;
            font-weight: 700;
            border-radius: var(--radius-md);
            position: relative;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            text-decoration: none;
        }

        .header-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 50%;
            height: 100%;
            background: linear-gradient(to right, transparent, rgba(255, 255, 255, 0.2), transparent);
            transform: skewX(-20deg);
            transition: left 0.5s ease;
        }

        .header-btn:hover::before {
            left: 150%;
        }

        .header-btn i {
            margin-right: 15px;
            font-size: 18px;
            color: var(--text-gold);
            transition: transform 0.3s;
        }

        .header-btn:hover {
            background: rgba(232, 200, 129, 0.2);
            box-shadow: 0 5px 20px rgba(232, 200, 129, 0.2);
            transform: translateY(-2px);
            color: #fff;
        }

        .header-btn:hover i {
            transform: scale(1.2);
        }
    </style>
    <div class="action-buttons">
        <a href="/download" class="header-btn btn-download"><i class="fas fa-download"></i> Game Download</a>
        <?php if (!empty($_SESSION['user'])): ?>
        <a href="/account" class="header-btn btn-register"><i class="fas fa-user"></i> My Account</a>
        <?php else: ?>
        <a href="/register" class="header-btn btn-register"><i class="fas fa-user-plus"></i> Free Registration</a>
        <?php endif; ?>
    </div>
</header>