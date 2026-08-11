<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\LE;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\ChunkPosition;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use function count;

class LevelChunkPacket extends DataPacket implements ClientboundPacket{
	public const NETWORK_ID = ProtocolInfo::LEVEL_CHUNK_PACKET;

	private const MAX_BLOB_HASHES = 64;

	private ChunkPosition $chunkPosition;
	/** @phpstan-var DimensionIds::* */
	private int $dimensionId;
	private int $subChunkCount;
	private ?int $subChunkRequestLimit;
	private bool $cacheEnabled;
	/** @var int[] */
	private array $usedBlobHashes = [];
	private string $extraPayload;

	/**
	 * @generate-create-func
	 * @param int[] $usedBlobHashes
	 * @phpstan-param DimensionIds::* $dimensionId
	 */
	public static function create(
		ChunkPosition $chunkPosition,
		int $dimensionId,
		int $subChunkCount,
		?int $subChunkRequestLimit,
		bool $cacheEnabled,
		array $usedBlobHashes,
		string $extraPayload,
	) : self{
		$result = new self;
		$result->chunkPosition = $chunkPosition;
		$result->dimensionId = $dimensionId;
		$result->subChunkCount = $subChunkCount;
		$result->subChunkRequestLimit = $subChunkRequestLimit;
		$result->cacheEnabled = $cacheEnabled;
		$result->usedBlobHashes = $usedBlobHashes;
		$result->extraPayload = $extraPayload;
		return $result;
	}

	public function getChunkPosition() : ChunkPosition{ return $this->chunkPosition; }

	public function getDimensionId() : int{ return $this->dimensionId; }

	public function getSubChunkCount() : int{
		return $this->subChunkCount;
	}

	public function getSubChunkRequestLimit() : ?int{
		return $this->subChunkRequestLimit;
	}

	public function isCacheEnabled() : bool{
		return $this->cacheEnabled;
	}

	/**
	 * @return int[]
	 */
	public function getUsedBlobHashes() : array{
		return $this->usedBlobHashes;
	}

	public function getExtraPayload() : string{
		return $this->extraPayload;
	}

	protected function decodePayload(ByteBufferReader $in, int $protocolId) : void{
		$this->chunkPosition = ChunkPosition::read($in);
		$this->dimensionId = VarInt::readSignedInt($in);

		$this->subChunkCount = VarInt::readUnsignedInt($in);
		$this->subChunkRequestLimit = CommonTypes::readOptional($in, VarInt::readSignedInt(...));

		$this->cacheEnabled = CommonTypes::getBool($in);
		$this->usedBlobHashes = [];
		$count = VarInt::readUnsignedInt($in);
		if($count > self::MAX_BLOB_HASHES){
			throw new PacketDecodeException("Expected at most " . self::MAX_BLOB_HASHES . " blob hashes, got " . $count);
		}
		for($i = 0; $i < $count; ++$i){
			$this->usedBlobHashes[] = LE::readUnsignedLong($in);
		}

		$this->extraPayload = CommonTypes::getString($in);
	}

	protected function encodePayload(ByteBufferWriter $out, int $protocolId) : void{
		$this->chunkPosition->write($out);
		VarInt::writeSignedInt($out, $this->dimensionId);

		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
			VarInt::writeUnsignedInt($out, $this->subChunkCount === \PHP_INT_MAX ? 0 : $this->subChunkCount);
		} else {
			VarInt::writeUnsignedInt($out, $this->subChunkCount);
		}
		
		CommonTypes::writeOptional($out, $this->subChunkRequestLimit, VarInt::writeSignedInt(...));

		CommonTypes::putBool($out, $this->cacheEnabled);
		VarInt::writeUnsignedInt($out, count($this->usedBlobHashes));
		foreach($this->usedBlobHashes as $hash){
			LE::writeUnsignedLong($out, $hash);
		}

		CommonTypes::putString($out, $this->extraPayload);
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handleLevelChunk($this);
	}
}