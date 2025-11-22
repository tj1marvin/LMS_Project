<div class="sidebar sidebar-style-2">
    <div class="sidebar-wrapper scrollbar scrollbar-inner">
        <div class="sidebar-content">
            <div class="user">
                <div class="avatar-sm float-left">
                    <img src="assets/img/admin_profile.png" alt="..." class="avatar-img rounded-circle">
                </div>
                <div class="info">
                    <a data-toggle="collapse" href="#profileDropdown" aria-expanded="false">
                        <span>
                            <?php echo isset($_SESSION['admin_username']) ? htmlspecialchars($_SESSION['admin_username']) : 'Admin'; ?>
                            <span class="user-level">Administrator</span>
                            <span class="caret"></span>
                        </span>
                    </a>
                    <div class="clearfix"></div>
                    <div class="collapse in" id="profileDropdown">
                        <ul class="nav nav-collapse">
                            <li>
                                <a href="admin-profile.php">
                                    <span class="link-collapse">Edit Profile</span>
                                </a>
                            </li>
                            <li>
                                <a href="#settings">
                                    <span class="link-collapse">Settings</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <ul class="nav nav-primary">
                <li class="nav-item active">
                    <a href="index.php" class="collapsed" aria-expanded="false">
                        <i class="fas fa-home"></i>
                        <p>Dashboard</p>
                    </a>
                </li>
                <li class="nav-section">
                    <span class="sidebar-mini-icon">
                        <i class="fa fa-ellipsis-h"></i>
                    </span>
                    <h4 class="text-section">Modules</h4>
                </li>
                <li class="nav-item">
                    <a data-toggle="collapse" href="#books">
                        <i class="fas fa-book"></i>
                        <p>Books</p>
                        <span class="caret"></span>
                    </a>
                    <div class="collapse" id="books">
                        <ul class="nav nav-collapse">
                            <li>
                                <a href="add-book.php">
                                    <span class="sub-item">Add Book</span>
                                </a>
                            </li>
                            <li>
                                <a href="view-books.php">
                                    <span class="sub-item">View Books</span>
                                </a>
                            </li>
                            <li>
                                <a href="categories.php">
                                    <span class="sub-item">Categories</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item">
                    <a data-toggle="collapse" href="#students">
                        <i class="fas fa-users"></i>
                        <p>Students</p>
                        <span class="caret"></span>
                    </a>
                    <div class="collapse" id="students">
                        <ul class="nav nav-collapse">
                            <li>
                                <a href="add-student.php">
                                    <span class="sub-item">Add Student</span>
                                </a>
                            </li>
                            <li>
                                <a href="view-students.php">
                                    <span class="sub-item">View Students</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item">
                    <a href="view-borrow-requests.php">
                        <i class="fas fa-hand-holding-heart"></i>
                        <p>Borrow Requests</p>
                    </a>
                </li>
                 <li class="nav-item">
                    <a href="view-issued-books.php">
                        <i class="fas fa-book-reader"></i>
                        <p>Issued Books</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="view-message.php">
                        <i class="fas fa-envelope"></i>
                        <p>Messages</p>
                    </a>
                </li>
                 <li class="nav-item">
                    <a data-toggle="collapse" href="#analytics">
                        <i class="fas fa-chart-bar"></i>
                        <p>Analytics</p>
                        <span class="caret"></span>
                    </a>
                    <div class="collapse" id="analytics">
                        <ul class="nav nav-collapse">
                            <li>
                                <a href="book-trends.php">
                                    <span class="sub-item">Book Issue Trends</span>
                                </a>
                            </li>
                            <li>
                                <a href="student-analytics.php">
                                    <span class="sub-item">Student Activity</span>
                                </a>
                            </li>
                             <li>
                                <a href="financial-report.php">
                                    <span class="sub-item">Financial Report</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
                <li class="nav-item">
                    <a href="view-notifications.php">
                        <i class="fas fa-bell"></i>
                        <p>Notifications</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="admin-profile.php">
                        <i class="fas fa-user-cog"></i>
                        <p>Admin Profile</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        <p>Logout</p>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</div>