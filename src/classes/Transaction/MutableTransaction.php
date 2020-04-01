<?php declare(strict_types=1);
class MutableTransaction implements Transaction {
    const PEER_READY = 1;
    const PEER_LOCKED = 2;
    const PEER_DOWN = 3;

    /**
     * @property array<string,Peer>
     */
    private $peers = [];

    /**
     * @property array<string,PeerStatus>
     */
    private $statuses = [];

    /**
     * @throw InvalidArgumentException if there is already a peer with the same ID
     */
    public function addPeer(Peer $peer) : void {
        if (array_key_exists($peer->getId(), $this->peers)) {
            throw new InvalidArgumentException("There is already a peer with that ID");
        }

        $this->peers[$peer->getId()] = $peer;
    }

    public function pickRandomUnmarkedPeer() : ?Peer {
        $eligiblePeers = array_values( // Rekey the array, otherwise gaps may exist
            array_diff(array_keys($this->peers), array_keys($this->statuses))
        );

        if (empty($eligiblePeers)) {
            return null;
        }

        $randomIndex = rand(0, count($eligiblePeers) - 1);
        $peerId = $eligiblePeers[$randomIndex];

        return $this->peers[$peerId];
    }

    public function getLastSequenceNumber(Peer $peer) : int {
        if (array_key_exists($peer->getId(), $this->statuses)) {
            $status = $this->statuses[$peer->getId()];

            if ($status->status === self::PEER_DOWN) {
                throw new InvalidArgumentException("This peer is down");
            }

            return $status->lastSequenceNumber;
        }
        else {
            throw new InvalidArgumentException("We don't know the last sequence number for that peer");
        }
    }

    public function getMostRecentSequenceNumber() : int {
        $peersReadyOrLocked = $this->getPeersReadyOrLocked();

        return max(array_map(function ($status) : int {
            return $status->lastSequenceNumber;
        }, $peersReadyOrLocked));
    }

    public function getPeersAtSequenceNumber(int $sequenceNumber) : array {
        $peersReadyOrLocked = $this->getPeersReadyOrLocked();

        $peersAtSequenceNumber = [];
        foreach ($peersReadyOrLocked as $peerId => $status) {
            if ($status->lastSequenceNumber === $sequenceNumber) {
                $peer = $this->peers[$peerId];
                $peersAtSequenceNumber[] = $peer;
            }
        }

        return $peersAtSequenceNumber;
    }

    private function getPeersReadyOrLocked() : array {
        return array_filter($this->statuses, function ($status) {
            return $status->status !== self::PEER_DOWN;
        });
    }

    public function markPeerReady(Peer $peer, int $lastKnownSequenceNumber) : void {
        $this->markPeer(
            $peer,
            new Transaction_PeerStatus(self::PEER_READY, $lastKnownSequenceNumber)
        );
    }

    public function markPeerLocked(Peer $peer, int $lastKnownSequenceNumber) : void {
        $this->markPeer(
            $peer,
            new Transaction_PeerStatus(self::PEER_LOCKED, $lastKnownSequenceNumber)
        );
    }

    public function markPeerDown(Peer $peer) : void {
        $this->markPeer(
            $peer,
            new Transaction_PeerStatus(self::PEER_DOWN)
        );
    }

    private function markPeer(Peer $peer, Transaction_PeerStatus $status) {
        if (!array_key_exists($peer->getId(), $this->peers)) {
            throw new InvalidArgumentException("Unknown peer");
        }

        if (array_key_exists($peer->getId(), $this->statuses)) {
            throw new InvalidArgumentException("Peer is already marked");
        }

        $this->statuses[$peer->getId()] = $status;
    }

    public function countPeers() : int {
        return count($this->peers);
    }

    public function countPeersReady() : int {
        return $this->countPeersWithStatus(self::PEER_READY);
    }

    public function countPeersLocked() : int {
        return $this->countPeersWithStatus(self::PEER_LOCKED);
    }

    public function countPeersDown() : int {
        return $this->countPeersWithStatus(self::PEER_DOWN);
    }

    private function countPeersWithStatus(int $requiredStatus) {
        return count(array_filter($this->statuses, function(Transaction_PeerStatus $status) use ($requiredStatus) : bool {
            return $status->status === $requiredStatus;
        }));
    }
}

class Transaction_PeerStatus {
    public $status; // TODO Rename this. $status->status isn't great, really
    public $lastSequenceNumber;

    public function __construct(int $status, int $lastSequenceNumber = null) {
        $this->status = $status;
        $this->lastSequenceNumber = $lastSequenceNumber;
    }
}
