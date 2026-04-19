<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class CreatePosts extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('posts');
        $table->addColumn('title', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('body', 'text', ['null' => false])
              ->addColumn('created', 'datetime')
              ->addColumn('modified', 'datetime')
              ->create();
    }
}
