<?php
/**
 * This choreographer kinda inverts the flow of data.
 * 
 * It will push instructions to multiple next nodes.
 * 
 * It may well be that not all next nodes answer the same way, depending on your setup.
 * Some may have additional sequence numbers, others different data (conflicting, perhaps).
 * 
 * This choreographer cannot guarantee the state of its children.
 * However it's still pretty good for distributing configuration, or spreading instructions to
 * otherwise unrelated nodes.
 */