<?php if (!empty($notice)): ?>
    <div class="notice <?php echo $notice['type']; ?>">
        <i class="<?php echo $notice['type'] === 'success' ? 'ri-checkbox-circle-line' : 'ri-error-warning-line'; ?>"></i>
        <?php echo $notice['text']; ?>
    </div>
<?php endif; ?>

<section class="tool-panel upload-panel">
    <div class="search-control">
        <i class="ri-search-line"></i>
        <input type="search" placeholder="Search <?php echo strtolower($pageType); ?> files" data-file-search>
    </div>
    <form class="upload-form" method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="upload">
        <select name="department" aria-label="College Department">
            <?php foreach ($departments as $department): ?>
                <option><?php echo $department; ?></option>
            <?php endforeach; ?>
        </select>
        <label class="file-picker">
            <i class="ri-attachment-2"></i>
            <span>Choose File</span>
            <input type="file" name="document" required>
        </label>
        <button class="primary-btn" type="submit"><i class="ri-upload-cloud-2-line"></i> Upload File</button>
    </form>
</section>

<section class="folder-grid">
    <?php foreach ($departments as $department): ?>
        <article class="folder-card">
            <div class="folder-icon"><i class="ri-folder-3-line"></i></div>
            <h3><?php echo $department; ?></h3>
            <p><?php echo folderFileCount($storageType, $department); ?> files stored</p>
        </article>
    <?php endforeach; ?>
</section>

<section class="panel">
    <div class="panel-heading">
        <h2><?php echo $pageType; ?> Files</h2>
        <span>Department folders and latest uploads</span>
    </div>
    <div class="file-grid">
        <?php foreach ($files as $file): ?>
            <article class="file-card" data-file-card>
                <div class="file-main">
                    <div class="file-icon"><i class="ri-file-text-line"></i></div>
                    <div>
                        <h3><?php echo $file['name']; ?></h3>
                        <p>Uploaded by <?php echo $file['user']; ?></p>
                    </div>
                </div>
                <dl class="file-meta">
                    <div><dt>Upload date</dt><dd><?php echo $file['date']; ?></dd></div>
                    <div><dt>Department</dt><dd><span class="dept-pill"><?php echo $file['department']; ?></span></dd></div>
                </dl>
                <div class="action-row">
                    <?php if (!empty($file['url'])): ?>
                        <a class="ghost-btn" href="<?php echo $file['url']; ?>" target="_blank"><i class="ri-eye-line"></i> Preview</a>
                        <a class="ghost-btn" href="<?php echo $file['url']; ?>" download><i class="ri-download-line"></i> Download</a>
                    <?php else: ?>
                        <button class="ghost-btn" type="button"><i class="ri-eye-line"></i> Preview</button>
                        <button class="ghost-btn" type="button"><i class="ri-download-line"></i> Download</button>
                    <?php endif; ?>
                    <?php if (!empty($file['stored'])): ?>
                        <form method="post">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="department" value="<?php echo $file['department']; ?>">
                            <input type="hidden" name="file" value="<?php echo $file['name']; ?>">
                            <button class="danger-btn" type="submit"><i class="ri-delete-bin-line"></i> Delete</button>
                        </form>
                    <?php else: ?>
                        <button class="danger-btn" type="button"><i class="ri-delete-bin-line"></i> Delete</button>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
