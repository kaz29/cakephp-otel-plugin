<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class Comment extends Entity
{
    protected array $_accessible = [
        'post_id' => true,
        'author' => true,
        'body' => true,
        'created' => true,
        'modified' => true,
        'post' => true,
    ];
}
