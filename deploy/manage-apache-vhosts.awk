BEGIN {
    while ((getline line < replacement) > 0) packaged[++packaged_count] = line
    close(replacement)
    if (packaged_count == 0) exit 2
}

function emit_block( i) {
    if (is_arbk) {
        arbk_count++
        if (arbk_count == 1) for (i=1; i<=packaged_count; i++) print packaged[i]
    } else {
        for (i=1; i<=block_count; i++) print block[i]
    }
    block_count=0
    is_arbk=0
}

/^[[:space:]]*<VirtualHost[[:space:]]/ {
    if (inside) exit 3
    inside=1
    block_count=0
    is_arbk=0
}

inside {
    block[++block_count]=$0
    directive=tolower($1)
    if (directive == "servername" && tolower($2) == "arbk.kryeqyteti.net") is_arbk=1
    if (directive == "serveralias") {
                for (i=2; i<=NF; i++) if (tolower($i) == "arbk.kryeqyteti.net") alias_arbk=1
    }
    if (/<\/VirtualHost>/) {
                if (alias_arbk && !is_arbk) {
                    fatal=5
                    exit 5
                }
        emit_block()
        inside=0
                alias_arbk=0
    }
    next
}

{ print }

END {
    if (fatal) exit fatal
    if (packaged_count == 0 || inside) exit 4
    if (arbk_count == 0) {
        print ""
        for (i=1; i<=packaged_count; i++) print packaged[i]
    }
}
