<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds editorial + media metadata + variants columns to the `files` table so
 * the table can back gallery-style features (covers, reorderable image
 * collections, alt text, captions). All columns are nullable so existing rows
 * remain valid.
 */
class EnrichFilesForGalleries extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('files', [
            'category' => [
                'type'       => 'VARCHAR',
                'constraint' => 16,
                'null'       => false,
                'default'    => 'document',
            ],
            'alt_text' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'caption' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'credit' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'width' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'height' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'duration_seconds' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'page_count' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'variants' => [
                'type' => 'JSON',
                'null' => true,
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('files', [
            'category',
            'alt_text',
            'caption',
            'credit',
            'width',
            'height',
            'duration_seconds',
            'page_count',
            'variants',
        ]);
    }
}
