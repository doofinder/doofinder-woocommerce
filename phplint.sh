
if [ ! -d "./vendor" ] 
then
    composer install
fi

phpVersions=("7.0" "7.1" "7.2" "7.3" "7.4" "8.0" "8.1" "8.2" "8.3" "8.4")

for phpVersion in ${phpVersions[@]}; do
    echo "---------------------------------------------"
    echo "---- Checking compatibility with PHP ${phpVersion} ----"
    echo "---------------------------------------------"
    ./vendor/bin/phpcs -p . --standard=PHPCompatibilityWP --ignore=*/tests/*,*/vendor/*,/html/* --extensions=php --runtime-set testVersion $phpVersion-
done
