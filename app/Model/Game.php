<?php

declare(strict_types=1);

namespace App\Model;

use flight\ActiveRecord;

/**
 * ActiveRecord model for board games.
 *
 * @property int         $id
 * @property int|null    $user_id
 * @property string      $name
 * @property string      $created_at
 * @property string      $status
 * @property string|null $ended_at
 * @property string|null $deleted_at
 */
class Game extends ActiveRecord
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CLOSED = 'closed';

    /**
     * @param mixed $databaseConnection PDO / SimplePdo / mysqli connection
     */
    public function __construct($databaseConnection)
    {
        parent::__construct($databaseConnection, 'games');
    }
}
