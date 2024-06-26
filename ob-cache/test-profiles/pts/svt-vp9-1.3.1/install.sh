#!/bin/sh

7z x Bosphorus_1920x1080_120fps_420_8bit_YUV_RAW.7z -aoa
7z x Bosphorus_3840x2160_120fps_420_8bit_YUV_Y4M.7z -aoa

tar -xf SVT-VP9-0.3.0.tar.gz
cd SVT-VP9-0.3.0/Build/linux
./build.sh release
echo $? > ~/install-exit-status

cd ~
echo "#!/bin/sh
./SVT-VP9-0.3.0/Bin/Release/SvtVp9EncApp \$@ > \$LOG_FILE 2>&1
echo \$? > ~/test-exit-status" > svt-vp9
chmod +x svt-vp9
