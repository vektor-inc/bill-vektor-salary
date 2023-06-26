#!/usr/bin/env bash

mkdir -p tests/themes

# ディレクトリに移動
cd tests/themes

# リポジトリをクローン
git clone https://github.com/vektor-inc/bill-vektor.git

cd bill-vektor

npm install

npm run build