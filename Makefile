# OPNsense plugins: BSD make only (.include). Do not use GNU gmake — use: make package
#
# This Makefile must live inside the official plugins tree, e.g.
#   opnsense/plugins/security/amneziawg/Makefile
# so ../../Mk/plugins.mk resolves (Mk lives under plugins/). A standalone clone in /tmp alone will not build.

.if exists(../../Mk/plugins.mk)
.else
.error ../../Mk/plugins.mk missing. Clone https://github.com/opnsense/plugins then copy this tree to plugins/security/amneziawg and run make package there (see README).
.endif

PLUGIN_NAME=     amneziawg
PLUGIN_VERSION=        1.1
PLUGIN_COMMENT=        AmneziaWG 2.0 VPN Plugin
PLUGIN_DEPENDS=        amnezia-kmod amnezia-tools
PLUGIN_MAINTAINER= antspopov@gmail.com

# Prerequisites for building dependencies:
# opnsense-code tools ports src
#
# Dependencies installation (from ports):
# amnezia-tools: cd /usr/ports/net/amnezia-tools && make install
# amnezia-kmod: cd /usr/ports/net/amnezia-kmod && make install
#   or: pkg install amnezia-tools amnezia-kmod (if available in package repository)

.include "../../Mk/plugins.mk"



