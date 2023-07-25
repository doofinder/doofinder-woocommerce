#!/bin/bash

echo "Let's install WordPress!"

# Pass through arguments to exec
if [ $# -ge 1 ]; then
  exec "$@"
fi
