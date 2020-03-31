<?php
/**
 * Instead of a chain, create a web of peers.
 * 
 * This is useful when you need HA write, but don't necessarily need
 * all the nodes to have the same data.
 * 
 * For example for logging facilities.
 * Clients should have all the peers' addresses and write to any one of them.
 */