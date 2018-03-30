namespace Prooph\EventStore;

data IpEndPoint = IpEndPoint { string $host, int $port };
data GossipSeed = GossipSeed { IpEndPoint $endPoint, string $hostHeader };