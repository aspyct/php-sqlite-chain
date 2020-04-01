<?php declare(strict_types=1);
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../src/classes.php';

final class QuorumChoreographerTest extends TestCase {
    /**
     * @property QuorumChoreographer
     */
    private $target;

    private $database;
    private $peerProvider;
    private $instruction;
    private $localPeer;

    public function setUp() : void {
        $this->database = $this->prophesize(Database::class);
        $this->peerProvider = $this->prophesize(PeerProvider::class);
        $this->instruction = $this->prophesize(Instruction::class);
        $this->localPeer = $this->prophesize(Peer::class);

        // Standard behavior
        $this->peerProvider->getLocalPeer()->willReturn($this->localPeer->reveal());

        $this->target = new QuorumChoreographer(
            $this->database->reveal(),
            $this->peerProvider->reveal()
        );
    }

    public function testThrowsIfLockCantBeAcquired() {
        $sequenceNumber = 1;

        $this->withLockedDatabase();
        $this->withLastSequenceNumber($sequenceNumber);

        $this->expectException(PeerLockedException::class);

        try {
            $this->target->runInstruction(
                $this->instruction->reveal()
            );
        }
        catch (PeerLockedException $e) {
            $this->assertSame($this->localPeer->reveal(), $e->getPeer());
            $this->assertEquals($sequenceNumber, $e->getLastSequenceNumber());

            throw $e;
        }
    }

    private function withLockedDatabase() : void {
        $this->database->lock()->willReturn(false);
    }

    private function withLastSequenceNumber(int $sequenceNumber) : void {
        $this->database->getLastSequenceNumber()->willReturn($sequenceNumber);
    }
}
