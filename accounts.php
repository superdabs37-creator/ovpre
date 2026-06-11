<?php
require_once 'includes/data.php';
require_once 'includes/layout.php';
renderHeader('Student Accounts', 'accounts');
?>
<section class="tool-panel">
    <div class="search-control">
        <i class="ri-search-line"></i>
        <input type="search" placeholder="Search student accounts">
    </div>
    <button class="primary-btn"><i class="ri-user-add-line"></i> Add Account</button>
</section>

<section class="panel">
    <div class="panel-heading">
        <h2>All Student Accounts</h2>
        <span>Manage registered users</span>
    </div>
    <div class="table-scroll">
        <table class="data-table account-table">
            <thead>
                <tr>
                    <th>ID Number</th>
                    <th>First Name</th>
                    <th>Middle Name</th>
                    <th>Last Name</th>
                    <th>Section</th>
                    <th>Year</th>
                    <th>Email</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $student): ?>
                    <tr>
                        <td><?php echo $student['id']; ?></td>
                        <td><?php echo $student['first']; ?></td>
                        <td><?php echo $student['middle']; ?></td>
                        <td><?php echo $student['last']; ?></td>
                        <td><?php echo $student['section']; ?></td>
                        <td><?php echo $student['year']; ?></td>
                        <td><?php echo $student['email']; ?></td>
                        <td>
                            <div class="action-row compact">
                                <button class="ghost-btn"><i class="ri-edit-line"></i></button>
                                <button class="danger-btn"><i class="ri-delete-bin-line"></i></button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="pagination">
        <button><i class="ri-arrow-left-s-line"></i></button>
        <button class="active">1</button>
        <button>2</button>
        <button>3</button>
        <button><i class="ri-arrow-right-s-line"></i></button>
    </div>
</section>
<?php renderFooter(); ?>
