-include .env
DIR=/usr/local/emhttp/plugins/docker.versions
SSH_HOST=${USERNAME}@${HOST}

push:
	scp -r src/docker.versions${DIR} ${SSH_HOST}:${DIR}

remove: 
	ssh ${SSH_HOST} 'rm -rf ${DIR}'