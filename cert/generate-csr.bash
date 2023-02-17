#!/bin/bash

openssl req -new -newkey rsa:2048 -nodes -keyout login.uab.pt.key -out login.uab.pt.csr -subj "/C=PT/ST=Portugal/O=Universidade Aberta/CN=login.uab.pt" -config <(cat /etc/ssl/openssl.cnf <(printf "[req]\ndistinguished_name=dn\n[dn]\n[ext]\nsubjectAltName=DNS:login.uab.pt\nkeyUsage=digitalSignature\nextendedKeyUsage=critical,1.3.6.1.5.5.7.3.1\n" ))
