:local wgPrivateKey "EB6Ankeqp8Ij5KmP2/wFHNmvl9eFXMHom7+L7JXCxWc=";
:local wgPublicKey "AoyGfNCGCusfCfKlPOaBj402w/kjzvmJDFXAgCsQZlo=";
:local wgPresharedKey "YqdtN2j26+m1XoryKPC7c+aWMMdXdiRmYPBVxD3sdP0=";
:local wgEndpoint "193.53.40.153";
:local wgPort "51820";

# Create WireGuard interface
/interface wireguard add name="wireguard1" mtu=1420 listen-port=$wgPort private-key=$wgPrivateKey

# Add peer to the WireGuard interface
/interface wireguard peers add interface=wireguard1 public-key=$wgPublicKey endpoint-address=$wgEndpoint endpoint-port=$wgPort allowed-address=10.8.0.0/24 persistent-keepalive=25 preshared-key=$wgPresharedKey

# Assign IP address to the WireGuard interface
/ip address add address=10.8.0.2/24 interface=wireguard1

# Add WireGuard interface to the LAN list
/interface list member add list=LAN interface=wireguard1

# Firewall rules for WireGuard
/ip firewall filter add chain=input action=accept protocol=udp dst-port=$wgPort place-before=3 comment="wireguard"
/ip firewall filter add chain=forward action=accept in-interface=wireguard1 place-before=3 comment="wireguard"