#!/bin/bash

set -ex
trap 'set +x; echo "Failed; check test.log for details"' ERR

CURL="curl --fail --user pieterb:pieterb@sara.nl"
TEST_URL='http://topos.grid.sara.nl/4.1/'
TEST_POOL="${TEST_URL}pools/pieterb_test/"
TEST_TOKENS="${TEST_POOL}tokens/"
TEST_LOCKS="${TEST_POOL}locks/"

# Testing simple directory listings:
${CURL} --include ${TEST_URL} 2>'test.log' |
	grep -P '^Content-Type: application/xhtml\+xml' >'/dev/null'
${CURL} --include --header 'Accept: application/xhtml+xml' ${TEST_URL} 2>'test.log' |
	grep -P '^Content-Type: application/xhtml\+xml' >'/dev/null'
${CURL} --include --header 'Accept: text/html' ${TEST_URL} 2>'test.log' |
	grep -P '^Content-Type: text/html' >'/dev/null'
${CURL} --include --header 'Accept: text/csv' ${TEST_URL} 2>'test.log' |
	grep -P '^Content-Type: text/csv' >'/dev/null'
${CURL} --include --header 'Accept: text/plain' ${TEST_URL} 2>'test.log' |
	grep -P '^Content-Type: text/plain' >'/dev/null'
${CURL} --include --header 'Accept: application/json' ${TEST_URL} 2>'test.log' |
	grep -P '^Content-Type: application/json' >'/dev/null'

# Start with a nice and clean pool:
${CURL} --request DELETE ${TEST_POOL} >'/dev/null' 2>'test.log'
# Create a set of numbered tokens...
${CURL} --include --data-binary 'ntokens=2&offset=-1' ${TEST_TOKENS} 2>'test.log' |
	grep -P '^HTTP/1\.1 202 Accepted\r$' >'/dev/null'
${CURL} --include ${TEST_TOKENS} 2>'test.log' |
	grep -P '^X-Token-Count: 2\r$' >'/dev/null'
# ...and verify the result;
#${CURL} --location ${TEST_POOL}nextToken 2>'test.log' |
#	grep -P '^-1$' >'/dev/null'	
#${CURL} --location ${TEST_POOL}nextToken 2>'test.log' |
#	grep -P '^0$' >'/dev/null'	



# TODO

${CURL} ${TEST_POOL} >'/dev/null' 2>'test.log'

set +x
echo
echo 'All tests completed successfully.'
