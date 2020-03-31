<?php
/**
 * A thin database only records the last known sequence number,
 * instead of the full instruction log.
 * Pretty good for live backups, if you make it a leaf node.
 * 
 * This makes it lighter on storage, but also prevents it from
 * feeding other nodes with updates.
 * 
 * It can and will, however, keep updated with regard to its own next node.
 * 
 * In short, it will feed off an InstructionLogDatabase, but nobody can feed off it.
 */
