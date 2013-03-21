[cache]
mode = apc; # values can be none / off / memcached / apc
timeout = 600; # value in seconds (or if change inode / file-timestamp)
memcached_host = "host:port";

[general]
stop_at_include = true; # change to false if you experience strange behaviour (will slow down a little)