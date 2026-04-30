import os, sys
os.chdir("/Users/alexandrechadenat/Documents/Claude/Site ACCIT")
import http.server, socketserver
port = 3456
handler = http.server.SimpleHTTPRequestHandler
with socketserver.TCPServer(("", port), handler) as httpd:
    httpd.serve_forever()
