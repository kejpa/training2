<?php

namespace App\Domain\Activity;

use App\Domain\ValueObject\ActivityId;
use App\Domain\ValueObject\UserId;
use stdClass;

class Activity implements \JsonSerializable {

    public function __construct(private ?ActivityId $id, private UserId $userId, private string $emoji, private string $name, private bool $logDistance,
        private bool $logTime, private string $distanceUnit) {
        if (!$this->id) {
            $this->id = new ActivityId();
        }
   }

    /**
     * @return ActivityId|null
     */
    public function getId(): ?ActivityId {
        return $this->id;
    }

    /**
     * @param ActivityId|null $id
     */
    public function setId(?ActivityId $id): void {
        $this->id = $id;
    }

    /**
     * @return UserId
     */
    public function getUserId(): UserId {
        return $this->userId;
    }

    /**
     * @param UserId $userId
     */
    public function setUserId(UserId $userId): void {
        $this->userId = $userId;
    }

    /**
     * @return string
     */
    public function getEmoji(): string {
        return $this->emoji;
    }

    /**
     * @param string $emoji
     */
    public function setEmoji(string $emoji): void {
        $this->emoji = $emoji;
    }

    /**
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void {
        $this->name = $name;
    }

    /**
     * @return bool
     */
    public function getLogDistance(): bool {
        return $this->logDistance;
    }

    /**
     * @param bool $logDistance
     */
    public function setLogDistance(bool $logDistance): void {
        $this->logDistance = $logDistance;
    }

    /**
     * @return bool
     */
    public function getLogTime(): bool {
        return $this->logTime;
    }

    /**
     * @param bool $logTime
     */
    public function setLogTime(bool $logTime): void {
        $this->logTime = $logTime;
    }

    /**
     * @return string
     */
    public function getDistanceUnit(): string {
        return $this->distanceUnit;
    }

    /**
     * @param string $distanceUnit
     */
    public function setDistanceUnit(string $distanceUnit): void {
        $this->distanceUnit = $distanceUnit;
    }

    public static function fromRow(array $row): self {
        return new self(new ActivityId($row['id']), new UserId($row['userid']), $row['emoji'], $row['name'],
            $row['log_distance'], $row['log_time'], $row['distance_unit']);
    }

    /**
     * @return array<string, mixed>
     */
    public function state(): array {

        return [
            'id' => $this->id->toString(),
            'userid' => $this->userId->toString(),
            'emoji' => $this->emoji,
            'name' => $this->name,
            'log_distance' => (int)$this->logDistance,
            'log_time' => (int)$this->logTime,
            'distance_unit' => $this->logDistance ? $this->distanceUnit : '',
        ];
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): stdClass {
        $me = new stdClass();

        $me->id = $this->id->toString();
        $me->userId = $this->userId->toString();
        $me->emoji = $this->emoji;
        $me->name = $this->name;
        $me->log_distance = $this->logDistance;
        $me->log_time = $this->logTime;
        $me->distance_unit = $this->distanceUnit;

        return $me;
    }
}