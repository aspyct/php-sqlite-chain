<?php
class PeerLockedException extends Exception {
    /**
     * @property Peer
     */
    private $peer;

    /**
     * @property int
     */
    private $lastSequenceNumber;

    public function __construct(Peer $peer, int $lastSequenceNumber) {
        $this->peer = $peer;
        $this->lastSequenceNumber = $lastSequenceNumber;
    }

    public function getPeer() : Peer {
        return $this->peer;
    }

    public function getLastSequenceNumber() : int {
        return $this->lastSequenceNumber;
    }
}
