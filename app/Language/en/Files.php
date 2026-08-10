<?php

declare(strict_types=1);

/**
 * File management language strings (English)
 */
return [
    // Success messages
    'upload_success'      => 'File uploaded successfully',
    'delete_success'      => 'File deleted successfully',
    'restore_success'     => 'File restored successfully',
    'force_delete_success' => 'File permanently deleted',

    // Error messages
    'file_required'       => 'File is required',
    'invalid_file_object'  => 'Invalid file object',
    'upload_failed'       => 'File upload failed: {0}',
    'file_too_large'       => 'File size exceeds maximum allowed size',
    'invalid_file_type'    => 'File type not allowed',
    'file_mime_mismatch'   => 'File content does not match its extension',
    'storage_failed'      => 'Failed to store file',
    'file_not_found'       => 'File not found or access denied',
    'id_required'         => 'File ID is required',
    'unauthorized'       => 'You are not authorized to access this file',
    'save_failed'         => 'Failed to save file metadata',
    'malware_detected'    => 'Malware or virus detected in the uploaded file',
    'temp_file_creation_failed' => 'Failed to create temporary file for virus scanning',
    'virus_scan_read_error' => 'Unable to read file for virus scanning',
    'virus_scan_unavailable' => 'Virus scanning is enabled, but no scanner is configured',

    // Request messages
    'invalid_request'     => 'Invalid request',
    'storage_error'       => 'Storage error',
    'notFound'           => 'Not found',
    'useDeleteWithContext' => 'Use delete() with FileGetRequestDTO to enforce ownership checks',
    'upload' => [
        'noFile' => 'No file was uploaded or file is invalid',
    ],

    // Trash / soft-delete
    'already_trashed'  => 'File is already in the trash',
    'not_trashed'      => 'File is not in the trash',
    'bulk_ids_required' => 'A non-empty list of file ids is required',
    'max_batch_ids' => 'A maximum of 200 file IDs may be resolved at once.',
    'max_batch_ids_hint' => 'Reduce the batch to 200 IDs or fewer.',
    'bulk_item_failed' => 'Operation failed for this file',

    // File references
    'in_use' => 'Cannot delete: this file is referenced by {0} resource(s). Unlink it first.',

    // Variant generation
    'not_an_image'       => 'Variant generation is only available for image files.',
    'regenerate_success' => 'Variants regenerated successfully.',

    // Metadata update
    'metadata_no_fields'    => 'At least one metadata field must be provided.',
    'metadata_update_success' => 'File metadata updated successfully.',

    // Replace
    'replace_success' => 'File replaced successfully.',

    // Stream hashing
    'hash_stream_invalid' => 'Expected a readable stream for file hashing.',
    'hash_stream_failed' => 'Failed to hash uploaded file stream.',
];
