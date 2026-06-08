<?php
define('BASE_PATH', dirname(__DIR__));

function assert_r2_contract($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

$storage = file_get_contents(BASE_PATH . '/includes/storage-functions.php');
$upload = file_get_contents(BASE_PATH . '/includes/upload-functions.php');
$image = file_get_contents(BASE_PATH . '/image.php');
$attachment = file_get_contents(BASE_PATH . '/attachment.php');
$r2_test = file_get_contents(BASE_PATH . '/bin/test-r2-storage.php');
$r2_backup = file_get_contents(BASE_PATH . '/bin/backup-attachments-to-r2.php');

assert_r2_contract(strpos($storage, "return 'tenants/' . max(1, \$tenant_id) . '/' . \$relative_path;") !== false, 'R2 object keys must be tenant-prefixed.');
assert_r2_contract(strpos($upload, "\$visibility !== 'public' && function_exists('storage_store_file')") !== false, 'Only private attachments should be moved to R2; public assets must stay local.');
assert_r2_contract(strpos($attachment, "storage_read_object(\$attachment)") !== false, 'Attachment download proxy must read R2 objects.');
assert_r2_contract(strpos($image, "storage_read_object(\$attachment)") !== false, 'Image proxy must support R2 image previews.');
assert_r2_contract(strpos($image, 'image_proxy_attachment_is_authorized') !== false, 'R2 image preview must use attachment authorization.');
assert_r2_contract(strpos($r2_test, 'storage_object_key($tenant_id') !== false, 'R2 smoke test must create tenant-prefixed keys.');
assert_r2_contract(strpos($r2_test, 'tenant_prefixed') !== false, 'R2 smoke test must report tenant prefix validation.');
assert_r2_contract(strpos($r2_test, "storage_r2_request('PUT'") !== false, 'R2 smoke test must upload an object.');
assert_r2_contract(strpos($r2_test, "storage_r2_request('GET'") !== false, 'R2 smoke test must download an object.');
assert_r2_contract(strpos($r2_test, "storage_r2_request('DELETE'") !== false, 'R2 smoke test must delete an object.');
assert_r2_contract(strpos($r2_backup, 'backups/attachments/') !== false, 'Attachment backup must write to a backup prefix outside app storage.');
assert_r2_contract(strpos($r2_backup, 'COALESCE(storage_driver') !== false, 'Attachment backup should target local/non-R2 attachments.');
assert_r2_contract(strpos($r2_backup, "storage_r2_request('PUT'") !== false, 'Attachment backup must upload local files to R2.');

echo "R2 storage contract tests passed\n";
