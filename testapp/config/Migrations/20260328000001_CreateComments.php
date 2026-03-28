<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class CreateComments extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('comments');
        $table->addColumn('post_id', 'integer', ['null' => false])
              ->addColumn('author', 'string', ['limit' => 100, 'null' => false])
              ->addColumn('body', 'text', ['null' => false])
              ->addColumn('created', 'datetime')
              ->addColumn('modified', 'datetime')
              ->addForeignKey('post_id', 'posts', 'id', [
                  'delete' => 'CASCADE',
                  'update' => 'NO_ACTION',
              ])
              ->create();
    }
}
