<?php
/**
 * The zero-log database does not keep any record of the instructions
 * or sequence numbers. It will never refuse a sequence number,
 * and will simply run the instructions it is given.
 * 
 * It doesn't play well with other logged databases like
 * - InstructionLogDatabase
 * - ThinDatabase
 * 
 * The last sequence number is always -1.
 */