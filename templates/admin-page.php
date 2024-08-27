<?php

namespace LVAI\FileVersionManager;

?>

<div class="wrap">
    <h1>File Version Manager</h1>
    <?php
    if (isset($_GET['update']) && isset($_GET['message'])) {
        $status = $_GET['update'] === 'success' ? 'updated' : 'error';
        $message = urldecode($_GET['message']);
        echo "<div class='notice $status is-dismissible'><p>$message</p></div>";
    }
    ?>

    <div id="fvm-upload-container" class="fvm-upload-container">
        <form method="post" enctype="multipart/form-data" id="fvm-upload-form">
            <?php wp_nonce_field('fvm_file_upload', 'fvm_file_upload_nonce'); ?>
            <div class="fvm-dropzone">
                <div class="upload-ui">
                    <h2 class="fvm-upload-instructions">Drop files to upload</h2>
                    <p class="fvm-upload-instructions">or</p>
                    <p id="fvm-file-name" style="display: none;"></p>
                    <input type="file" name="file" id="fvm-file-input" style="display: none;" required>
                    <button type="button" id="fvm-select-file" class="browser button button-hero">Select File</button>
                    <input type="submit" name="fvm_upload_file" id="fvm-upload-button" value="Upload File"
                        class="button button-primary button-hero" style="display: none;">
                </div>
                <div class="post-upload-ui" id="post-upload-info">
                    <p class="fvm-upload-instructions">
                        Maximum upload file size: <?php echo size_format(wp_max_upload_size()); ?>.
                    </p>
                </div>
            </div>
        </form>
    </div>

    <div id="edit-form-container" style="display:none;">
        <form id="edit-form" method="post" enctype="multipart/form-data">
            <!-- The content of the edit form will be dynamically inserted here -->
        </form>
    </div>

    <form method="post">
        <?php
        wp_nonce_field('bulk-files');
        $this->wp_list_table->prepare_items();
        $this->wp_list_table->display_bulk_action_result();
        $this->wp_list_table->search_box('Search', 'search');
        $this->wp_list_table->display();
        ?>
    </form>

    <?php
    // Add modals for each file
    foreach ($this->wp_list_table->items as $item) {
        echo $this->wp_list_table->get_edit_form_html($item['id'], $item);
    }
    ?>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const dropzone = document.querySelector('.fvm-dropzone');
            const fileInput = document.getElementById('fvm-file-input');
            const selectFileBtn = document.getElementById('fvm-select-file');
            const uploadForm = document.getElementById('fvm-upload-form');
            const uploadButton = document.getElementById('fvm-upload-button');
            const fileNameDisplay = document.getElementById('fvm-file-name');
            const uploadInstructions = document.querySelectorAll('.fvm-upload-instructions');
            const postUploadInfo = document.getElementById('post-upload-info');

            // Drag and drop functionality
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, preventDefaults, false);
            });

            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            ['dragenter', 'dragover'].forEach(eventName => {
                dropzone.addEventListener(eventName, highlight, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, unhighlight, false);
            });

            function highlight() {
                dropzone.classList.add('highlight');
            }

            function unhighlight() {
                dropzone.classList.remove('highlight');
            }

            dropzone.addEventListener('drop', handleDrop, false);

            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                handleFiles(files);
            }

            // Select file button functionality
            selectFileBtn.addEventListener('click', () => {
                fileInput.click();
            });

            fileInput.addEventListener('change', () => {
                handleFiles(fileInput.files);
            });

            function handleFiles(files) {
                if (files.length > 0) {
                    const fileName = files[0].name;
                    fileNameDisplay.textContent = fileName;
                    fileNameDisplay.style.display = 'block';
                    selectFileBtn.style.display = 'none';
                    if (uploadButton) {
                        uploadButton.style.display = 'inline-block';
                    }
                    // Hide upload instructions and post-upload info
                    uploadInstructions.forEach(el => el.style.display = 'none');
                    if (postUploadInfo) {
                        postUploadInfo.style.display = 'none';
                    }
                }
            }

            // ... rest of your JavaScript ...
        });
    </script>

    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.copy-shortcode').forEach(button => {
                button.addEventListener('click', function (e) {
                    e.preventDefault();
                    const shortcode = this.getAttribute('data-shortcode');
                    copyToClipboard(shortcode, this);
                });
            });

            function copyToClipboard(text, button) {
                const textArea = document.createElement("textarea");
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();

                try {
                    document.execCommand('copy');
                    const originalText = button.textContent;
                    button.textContent = 'Copied!';
                    setTimeout(function () {
                        button.textContent = originalText;
                    }, 2000);
                } catch (err) {
                    console.error('Unable to copy to clipboard', err);
                }

                document.body.removeChild(textArea);
            }

            document.querySelectorAll('.edit-file').forEach(link => {
                link.addEventListener('click', function (e) {
                    e.preventDefault();
                    const fileId = this.getAttribute('data-file-id');
                    const modal = document.getElementById('edit-modal-' + fileId);
                    modal.style.display = 'block';
                });
            });

            document.querySelectorAll('.close, .cancel-edit').forEach(button => {
                button.addEventListener('click', function (e) {
                    e.preventDefault();
                    const modal = this.closest('.edit-modal');
                    modal.style.display = 'none';
                });
            });

            window.onclick = function (event) {
                if (event.target.classList.contains('edit-modal')) {
                    event.target.style.display = 'none';
                }
            }


        });
    </script>
</div>