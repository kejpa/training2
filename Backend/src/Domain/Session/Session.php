<?php

namespace App\Domain\Session;

use App\Domain\ValueObject\ActivityId;
use App\Domain\ValueObject\SessionId;
use App\Domain\ValueObject\UserId;
use DateTimeImmutable;
use DateTimeInterface;
use JsonSerializable;
use stdClass;

class Session implements JsonSerializable {

    public function __construct(private ?SessionId $id, private UserId $userId, private ActivityId $activityId, private DateTimeInterface $date,
        private ?string $duration, private ?float $distance, private string $description, private int $rpe) {

    }

    public static function fromRow(array $row): self {
        return new self(new SessionId($row['id']), new UserId($row['userid']), new ActivityId($row['activityid']),
            DateTimeImmutable::createFromFormat('Y-m-d', $row['date']), $row['duration'], $row['distance'], $row['description'], $row['rpe']);
    }

    public function state(): array {
        return [
            'id' => $this->id->toString(),
            'userid' => $this->userId->toString(),
            'activityid' => $this->activityId->toString(),
            'date' => $this->date->format('Y-m-d'),
            'duration' => $this->duration,
            'distance' => $this->distance,
            'description' => $this->description,
            'rpe' => $this->rpe,
        ];
    }

    /**
     * @return SessionId|null
     */
    public function getId(): ?SessionId {
        return $this->id;
    }

    /**
     * @param SessionId $id
     */
    public function setId(SessionId $id): void {
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
     * @return ActivityId
     */
    public function getActivityId(): ActivityId {
        return $this->activityId;
    }

    /**
     * @param ActivityId $activityId
     */
    public function setActivityId(ActivityId $activityId): void {
        $this->activityId = $activityId;
    }

    /**
     * @return DateTimeInterface
     */
    public function getDate(): DateTimeInterface {
        return $this->date;
    }

    /**
     * @param DateTimeInterface $date
     */
    public function setDate(DateTimeInterface $date): void {
        $this->date = $date;
    }

    /**
     * @return string|null
     */
    public function getDuration(): ?string {
        return $this->duration;
    }

    /**
     * @param string|null $duration
     */
    public function setDuration(?string $duration): void {
        $this->duration = $duration;
    }

    /**
     * @return float|null
     */
    public function getDistance(): ?float {
        return $this->distance;
    }

    /**
     * @param float|null $distance
     */
    public function setDistance(?float $distance): void {
        $this->distance = $distance;
    }

    /**
     * @return string
     */
    public function getDescription(): string {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription(string $description): void {
        $this->description = $description;
    }

    /**
     * @return int
     */
    public function getRpe(): int {
        return $this->rpe;
    }

    /**
     * @param int $rpe
     */
    public function setRpe(int $rpe): void {
        $this->rpe = $rpe;
    }

    public function jsonSerialize(): stdClass {
        $me = new stdClass();

        $me->id = $this->id->toString();
        $me->userid = $this->userId->toString();
        $me->activityid = $this->activityId->toString();
        $me->date = $this->date->format('Y-m-d');
        $me->duration = $this->duration;
        $me->distance = $this->distance;
        $me->description = $this->description;
        $me->rpe = $this->rpe;

        return $me;
    }
}