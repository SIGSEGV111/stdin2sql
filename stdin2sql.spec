Name:           stdin2sql
Summary:        Execute a SQL statement once per stdin line with optional placeholder binding
Group:          Applications/Databases
Distribution:   openSUSE
License:        GPLv3
URL:            https://www.brennecke-it.net
BuildArch:      noarch
BuildRequires:  go-md2man easy-rpm
Requires:       php-cli php-pgsql

%description
%{name}.php reads stdin line by line and runs one SQL statement per line.
If the SQL contains $1, the program binds the current line to that placeholder
and executes using libpq via the php-pgsql extension.

%prep
%setup -q -n %{name}

%build
make %{?_smp_mflags} VERSION="Version %{version}"

%install
make install CONFDIR=%{buildroot}%{_sysconfdir} BINDIR=%{buildroot}%{_bindir} MANDIR="%{buildroot}%{_mandir}" UNITDIR="%{buildroot}%{_unitdir}"

%files
%{_bindir}/%{name}.php
%{_mandir}/man1/%{name}.1.gz

%changelog
