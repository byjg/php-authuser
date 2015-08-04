-- ------------------------------
create table users
(
    userid integer identity not null,
    name varchar(50),
    email varchar(120),
    username varchar(20) not null,
    password char(40) not null,
    created datetime,
    admin enum('Y','N'), 
    
   	constraint pk_users primary key (userid)
) 
TYPE = InnoDB;

-- ------------------------------
create table users_property
(
   customid integer identity not null,
   name varchar(50),
   value varchar(255),
   userid integer not null,
   
   constraint pk_users_property primary key (customid),
   constraint fk_users_userid foreign key (userid) references users_property (userid),
) 
TYPE = InnoDB;

-- New user 
-- username: admin
-- password: pwd)
insert into users (name, email, username, password, created, admin) 
values 
   ('Administrator', 'your@email.com', 'admin', '37FA265330AD83EAA879EFB1E2DB6380896CF639', now(), 'yes' );