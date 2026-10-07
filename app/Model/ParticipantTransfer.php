<?php

declare(strict_types=1);

namespace App\Model;

use flight\ActiveRecord;

/**
 * ActiveRecord model for participant money transfers.
 *
 * @property int    $id
 * @property int    $game_id
 * @property int    $from_participant_id
 * @property int    $to_participant_id
 * @property int    $amount
 * @property string $created_at
 */
class ParticipantTransfer extends ActiveRecord
{
    /**
     * @param mixed $databaseConnection PDO / SimplePdo / mysqli connection
     */
    public function __construct($databaseConnection)
    {
        parent::__construct($databaseConnection, 'participant_transfers');
    }
}
