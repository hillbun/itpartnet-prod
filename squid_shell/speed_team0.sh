#!/bin/bash

#sar -n DEV 1 1 | awk -v IF=team0  -v sp=1000 '
#$0 ~ IF {
#rxkb=$5; txkb=$6;
#rxmb=rxkb*8/1024;
#txmb=txkb*8/1024;
#tot=rxmb + txmb;
#printf "IF=%s rx=%.1fMb/s tx=%.1fMb/s total=%.1fMb/s (%.1f%% of %dMb/s)\n",
#    IF, rxmb, txmb, tot, tot*100/sp, sp;
#    }'


sar -n DEV 1 10 | awk -v IF=ens3f0  -v sp=2000 '$0 ~ IF {rxkb=$5; txkb=$6; rxmb=rxkb*8/1024; txmb=txkb*8/1024; tot=rxmb + txmb; printf "IF=%s rx=%.1fMb/s tx=%.1fMb/s total=%.1fMb/s (%.1f%% of %dMb/s)\n", IF, rxmb, txmb, tot, tot*100/sp, sp; }'

