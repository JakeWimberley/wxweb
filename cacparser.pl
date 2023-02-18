# generates CAC data file for EpiTAF
# input is the big CAC spreadsheet posted by NWS HQ
# output is to stdout, can be piped to cac.js and placed in same dir with EpiTAF

use strict;

my @cats = ['Below Minima','Cat A','Cat B','Cat C','Cat D']; #,'Cat E','Cat F'];
my $lineCount = 0;
LINE: foreach my $line (<>) {
	$lineCount++;
	chomp;
	if ($lineCount == 1) {
		if ($line =~ /"(.+)"/) {
			print "var cacDate = '" . $1 . "';\n";
		} else {
			print "var cacDate = 'Unknown';\n";
		}
			print "var cacThresholds = {\n";
	}
	my @fields = split(/,/,$line);
	next if ($lineCount < 10);
	my $airport = '';
	if ($fields[0] =~ /^[A-Z0-9]{3,4}/ && $fields[1] =~ /^\d+\s*-\s*/) { # found ID plus the Cat A wx criteria
		if ($fields[0] =~ /(^[A-Z0-9]{3,4})/) {
			$airport = $1;
			$airport = "K$airport" if (length($airport) == 3);
		}
		my @cigs = [];
		my @vsbys = [];
		$cigs[0] = -1;
		$vsbys[0] = -1;
		for (my $i = 1; $i <= 4; $i++) {
			if ($fields[$i] =~ /^(\d+)\s*-\s*(.+)/) {
				push(@cigs,$1);
				my $vsby = $2;
				if ($vsby =~ m!/!) {
					$vsby =~ m!(\d )?(\d+)/(\d+)!;
					if ($1) {
						push(@vsbys,$1+($2/$3)) if ($3);
					} else {
						push(@vsbys,$2/$3) if ($3);
					}
				} else {
					push(@vsbys,$vsby);
				}
			} else {
				print "// Could not parse data for '" . $fields[0] . "'\n";
				next LINE;
			}
		}
		my $cigMins = '[' . join(',', @cigs) . ']';
		my $visMins = '[' . join(',', @vsbys) . ']';
		print <<EOT;
	'$airport': {
	    'cigMins': $cigMins,
	    'visMins': $visMins,
	},
EOT
	}
}
print "};\n";
