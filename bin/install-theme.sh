#!/usr/bin/env bash

mkdir -p temp/themes

# ディレクトリに移動
cd temp/themes

# リポジトリをクローン
git clone https://github.com/vektor-inc/bill-vektor.git

cd bill-vektor

npm install

npm run build