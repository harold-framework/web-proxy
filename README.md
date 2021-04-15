# web-proxy
A proxy to sit inbetween clients and your Harold API instance. Allows the PHP page to take ``GET`` and ``POST`` requests along with URL parameters and headers, to the internal Harold API.
It also sends the ``X-Require-Authentication`` to the Harold middleware to ignore it if its a whitelisted IP, Meaning clients will still have to use the key based design.

## Why is this better?
Using this acts to proxy requests so clients don't have to know the internal API. It also helps if the harold instance is running on a private domain / IP address.
