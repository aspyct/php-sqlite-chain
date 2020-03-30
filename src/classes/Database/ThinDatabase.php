<?php
/**
 * A thin database only records the last known sequence number,
 * instead of the full instruction log.
 * 
 * This makes it lighter on storage, but also prevents it from
 * feeding other nodes with updates.
 * 
 * It can and will, however, keep updated with regard to its own next node.
 */
