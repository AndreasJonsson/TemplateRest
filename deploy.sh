#!/bin/bash -e

dry_run=0

dir="$(cd "$(dirname "$BASH_SOURCE")"; pwd -P)"

. $dir/deploy.conf

extName=TemplateRest

. mw-deploy-extension-via-git.sh

