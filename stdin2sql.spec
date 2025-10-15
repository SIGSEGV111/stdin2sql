Name:           stdin2sql
Summary:        Execute a SQL statement once per stdin line with optional placeholder binding
Group:          Applications/Databases
Distribution:   openSUSE
License:        GPLv3
URL:            https://www.brennecke-it.net
BuildArch:      noarch
BuildRequires:  go-md2man
Requires:       php-cli, php-pgsql, krb5-client

%description
stdin2sql.php reads stdin line by line and runs one SQL statement per line.
If the SQL contains $1, the program binds the current line to that placeholder
and executes using libpq via the php-pgsql extension. Kerberos/GSSAPI
authentication is supported through the system's libpq configuration
(no username or password required).

%prep
%setup -q -n stdin2sql

%build
make %{?_smp_mflags} VERSION="Version %{version}"

%install
make install BINDIR=%{buildroot}%{_bindir} MANDIR="%{buildroot}%{_mandir}"

%files
%{_bindir}/stdin2sql.php
%{_mandir}/man1/stdin2sql.1.gz

%changelog
