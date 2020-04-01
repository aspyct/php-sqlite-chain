<?php declare(strict_types=1);
use PHPUnit\Framework\TestCase;

class MutableTransactionTest extends TestCase {
    /**
     * @property MutableTransaction
     */
    private $target;

    public function setUp() : void {
        $this->target = new MutableTransaction();
    }

    public function testCountsAllPeers() {
        $this->assertEquals(0, $this->target->countPeers());

        $this->target->addPeer($this->createRandomPeer());

        $this->assertEquals(1, $this->target->countPeers());
        $this->assertEquals(0, $this->target->countPeersLocked());
        $this->assertEquals(0, $this->target->countPeersDown());
    }

    public function testCannotAddExistingPeer() {
        $peer = $this->createRandomPeer();
        $this->target->addPeer($peer);

        $this->expectException(InvalidArgumentException::class);
        $this->target->addPeer($peer);
    }

    public function testMarksPeerReady() {
        $peer = $this->createRandomPeer();
        $sequenceNumber = 1;

        $this->target->addPeer($peer);
        $this->target->markPeerReady($peer, $sequenceNumber);

        $this->assertEquals(1, $this->target->countPeersReady());
        $this->assertEquals(0, $this->target->countPeersLocked());
        $this->assertEquals(0, $this->target->countPeersDown());
    }

    public function testMarksPeerLocked() {
        $peer = $this->createRandomPeer();
        $sequenceNumber = 1;

        $this->target->addPeer($peer);
        $this->target->markPeerLocked($peer, $sequenceNumber);

        $this->assertEquals(0, $this->target->countPeersReady());
        $this->assertEquals(1, $this->target->countPeersLocked());
        $this->assertEquals(0, $this->target->countPeersDown());
    }

    public function testMarksPeerDown() {
        $peer = $this->createRandomPeer();
        $sequenceNumber = 1;

        $this->target->addPeer($peer);
        $this->target->markPeerDown($peer);

        $this->assertEquals(0, $this->target->countPeersReady());
        $this->assertEquals(0, $this->target->countPeersLocked());
        $this->assertEquals(1, $this->target->countPeersDown());
    }

    public function testCannotMarkPeerTwice() {
        $peer = $this->createRandomPeer();
        $this->target->addPeer($peer);
        $this->target->markPeerReady($peer, 42);

        $this->expectException(InvalidArgumentException::class);
        $this->target->markPeerReady($peer, 42);
    }

    public function testCannotMarkUnkownPeer() {
        $peer = $this->createRandomPeer();

        $this->expectException(InvalidArgumentException::class);
        $this->target->markPeerDown($peer);
    }

    public function testKnowsSequenceNumbersForPeers() {
        $peer1 = $this->createRandomPeer();
        $peer2 = $this->createRandomPeer();
        $peer3 = $this->createRandomPeer();

        $this->target->addPeer($peer1);
        $this->target->addPeer($peer2);
        $this->target->addPeer($peer3);

        $this->target->markPeerReady($peer1, 41);
        $this->target->markPeerLocked($peer2, 42);
        $this->target->markPeerDown($peer3);

        $this->assertEquals(42, $this->target->getMostRecentSequenceNumber());
        $this->assertEquals([$peer1], $this->target->getPeersAtSequenceNumber(41));
        $this->assertEquals([$peer2], $this->target->getPeersAtSequenceNumber(42));
    }

    public function testPicksRandomUnmarkedPeers() {
        // Unit testing random behavior is always pretty bad.
        // Let's test with a lot of peers. Hopefully this should be stable enough.
        $peers = $this->createAndAddRandomPeers(100);

        while ($randomPeer = $this->target->pickRandomUnmarkedPeer()) {
            $key = array_search($randomPeer, $peers);
            $this->assertNotFalse($key);

            unset($peers[$key]);

            switch (count($peers) % 3) {
                case 0: $this->target->markPeerReady($randomPeer, 42); break;
                case 1: $this->target->markPeerLocked($randomPeer, 42); break;
                case 2: $this->target->markPeerDown($randomPeer); break;
            }
        }
    }

    public function testThrowsWhenRequestingLastSequenceNumberForUnknownPeer() {
        $peer = $this->createRandomPeer();

        $this->expectException(InvalidArgumentException::class);
        $this->target->getLastSequenceNumber($peer);
    }

    public function testThrowsWhenRequestingLastSequenceNumberForUnmarkedPeer() {
        $peer = $this->createRandomPeer();
        $this->target->addPeer($peer);

        $this->expectException(InvalidArgumentException::class);
        $this->target->getLastSequenceNumber($peer);
    }

    public function testThrowsWhenRequestingLastSequenceNumberForDownPeer() {
        $peer = $this->createRandomPeer();
        $this->target->addPeer($peer);
        $this->target->markPeerDown($peer);

        $this->expectException(InvalidArgumentException::class);
        $this->target->getLastSequenceNumber($peer);
    }

    public function testReturnsLastSequenceNumberForReadyPeer() {
        $sequenceNumber = 42;
        $peer = $this->createRandomPeer();
        $this->target->addPeer($peer);
        $this->target->markPeerReady($peer, $sequenceNumber);

        $this->assertEquals($sequenceNumber, $this->target->getLastSequenceNumber($peer));
    }

    public function testReturnsLastSequenceNumberForLockedPeer() {
        $sequenceNumber = 42;
        $peer = $this->createRandomPeer();
        $this->target->addPeer($peer);
        $this->target->markPeerLocked($peer, $sequenceNumber);

        $this->assertEquals($sequenceNumber, $this->target->getLastSequenceNumber($peer));
    }

    private function createRandomPeer() : Peer {
        $peer = $this->prophesize(Peer::class);
        $peer->getId()->willReturn(uuidv4());
        $peer->getUrl()->willReturn("https://somenode.net/php");

        return $peer->reveal();
    }

    private function createAndAddRandomPeer() : Peer {
        $peer = $this->createRandomPeer();
        $this->target->addPeer($peer);

        return $peer;
    }

    private function createAndAddRandomPeers(int $count) {
        return array_map(function ($_) {
            return $this->createAndAddRandomPeer();
        }, array_fill(0, $count, null));
    }
}
