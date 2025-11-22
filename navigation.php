

<div class="main-header">
    <div class="logo-header">
        <a href="index.php" class="logo">
            <img src="assets/img/lms_logo.svg" alt="LMS Logo" class="navbar-brand" style="width: 40px;"> <span>LMS Admin</span>
        </a>
        <button class="navbar-toggler sidenav-toggler ml-auto" type="button" data-toggle="collapse" data-target="collapse" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon">
                <i class="icon-menu"></i>
            </span>
        </button>
        <button class="topbar-toggler more"><i class="icon-options-vertical"></i></button>
        <div class="nav-toggle">
            <button class="btn btn-toggle-mini btn-round pull-right">
                <i class="icon-options-vertical"></i>
            </button>
        </div>
    </div>
    <nav class="navbar navbar-header navbar-expand-lg">
        <div class="container-fluid">
            <ul class="navbar-nav topbar-nav ml-md-auto align-items-center">
                <li class="nav-item dropdown hidden-caret">
                    <a class="nav-link dropdown-toggle" href="#" id="notifDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fa fa-bell"></i>
                         <span class="notification">3</span>
                    </a>
                    <ul class="dropdown-menu notif-box animated fadeIn" aria-labelledby="notifDropdown">
                        <li>
                            <div class="dropdown-title">You have 4 new notifications</div>
                        </li>
                        <li>
                            <div class="notif-scroll scrollbar-outer">
                                <div class="notif-center">
                                    <a href="#">
                                        <div class="notif-icon notif-primary"> <i class="fa fa-user-plus"></i> </div>
                                        <div class="notif-content">
                                            <span class="block">
                                                New user registered
                                            </span>
                                            <span class="time">5 minutes ago</span>
                                        </div>
                                    </a>
                                    <a href="#">
                                        <div class="notif-icon notif-success"><i class="fa fa-book"></i> </div>
                                        <div class="notif-content">
                                            <span class="block">
                                                New book added
                                            </span>
                                            <span class="time">12 minutes ago</span>
                                        </div>
                                    </a>
                                    <a href="#">
                                        <div class="notif-icon notif-danger"> <i class="fa fa-heart"></i> </div>
                                        <div class="notif-content">
                                            <span class="block">
                                                Student book request
                                            </span>
                                            <span class="time">35 minutes ago</span>
                                        </div>
                                    </a>
                                    <a href="#">
                                        <div class="notif-icon notif-info"> <i class="fa fa-envelope"></i> </div>
                                        <div class="notif-content">
                                            <span class="block">
                                                New message received
                                            </span>
                                            <span class="time">1 hour ago</span>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </li>
                        <li>
                            <a class="see-all" href="view-notifications.php">See all notifications<i class="fa fa-angle-right"></i> </a>
                        </li>
                    </ul>
                </li>
                 <li class="nav-item dropdown hidden-caret">
                    <a class="nav-link dropdown-toggle" href="#" id="messageDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fa fa-envelope"></i>
                         <span class="notification"><?php echo isset($unreadMessagesCount) ? htmlspecialchars($unreadMessagesCount) : '0'; ?></span>
                    </a>
                    <ul class="dropdown-menu messages-notif-box animated fadeIn" aria-labelledby="messageDropdown">
                        <li>
                            <div class="dropdown-title">You have <?php echo isset($unreadMessagesCount) ? htmlspecialchars($unreadMessagesCount) : 'No'; ?> new message</div>
                        </li>
                        <li>
                            <div class="message-notif-scroll scrollbar-outer">
                                <div class="notif-center">
                                     <?php if(isset($recentMessages) && !empty($recentMessages)): ?>
                                        <?php foreach($recentMessages as $recentMessage): ?>
                                            <a href="view-message.php">
                                                <div class="notif-item">
                                                    <div class="content">
                                                        <div class="user"><b><?php echo htmlspecialchars($recentMessage['sender_name']); ?></b> <span class="float-right time"><?php echo htmlspecialchars(date('M d', strtotime($recentMessage['timestamp']))); ?></span></div>
                                                        <p><?php echo htmlspecialchars(substr($recentMessage['message_content'], 0, 40)); ?>...</p>
                                                    </div>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="notif-item">
                                            <div class="content">
                                                <p>No recent messages.</p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </li>
                        <li>
                            <a class="see-all" href="view-message.php">See all messages<i class="fa fa-angle-right"></i> </a>
                        </li>
                    </ul>
                </li>
                <li class="nav-item dropdown hidden-caret">
                    <a class="nav-link dropdown-toggle profile-pic" data-toggle="dropdown" href="#" aria-expanded="false">
                        <div class="avatar-sm">
                            <img src="assets/img/admin_profile.png" alt="..." class="avatar-img rounded-circle">
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-user animated fadeIn">
                        <div class="dropdown-user-scroll scrollbar-outer">
                            <li>
                                <div class="user-box">
                                    <div class="avatar-lg"><img src="assets/img/admin_profile.png" alt="image profile" class="avatar-img rounded"></div>
                                    <div class="u-text">
                                        <h4><?php echo isset($_SESSION['admin_username']) ? htmlspecialchars($_SESSION['admin_username']) : 'Admin'; ?></h4>
                                        <p class="text-muted">administrator</p><a href="admin-profile.php" class="btn btn-xs btn-secondary btn-sm">View Profile</a>
                                    </div>
                                </div>
                            </li>
                            <li>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="admin-profile.php">Account Setting</a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="logout.php">Logout</a>
                            </li>
                        </div>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>
    </div>