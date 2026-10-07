<?php

declare(strict_types=1);

namespace App\Model;

use flight\ActiveRecord;

/**
 * ActiveRecord model for game participants.
 *
 * @property int         $id
 * @property int         $game_id
 * @property string      $name
 * @property int         $balance
 * @property string      $created_at
 * @property string      $updated_at
 * @property string|null $deleted_at
 */
class Participant extends ActiveRecord
{
    public const DEFAULT_NAME = 'Negara';

    /**
     * @param mixed $databaseConnection PDO / SimplePdo / mysqli connection
     */
    public function __construct($databaseConnection)
    {
        parent::__construct($databaseConnection, 'participants');
    }
}
