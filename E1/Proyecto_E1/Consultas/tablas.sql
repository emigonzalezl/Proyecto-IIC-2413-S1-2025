-- Region
CREATE TABLE Region (
    id_region INT PRIMARY KEY,
    nombre VARCHAR(100)
);

-- Comuna
CREATE TABLE Comuna (
    id_comuna INT PRIMARY KEY,
    nombre VARCHAR(100),
    id_region INT,
    FOREIGN KEY (id_region) REFERENCES Region(id_region)
);

-- Persona
CREATE TABLE Persona (
    RUN VARCHAR(20) PRIMARY KEY,
    nombre_completo VARCHAR(100),
    correo VARCHAR(100),
    direccion_calle VARCHAR(100),
    telefono_celular VARCHAR(20),
    telefono_alternativo VARCHAR(20),
    fecha_nacimiento DATE,
    id_comuna INT,
    FOREIGN KEY (id_comuna) REFERENCES Comuna(id_comuna)
);

-- Sucursal
CREATE TABLE Sucursal (
    id_sucursal INT PRIMARY KEY,
    nombre VARCHAR(100),
    id_comuna INT,
    FOREIGN KEY (id_comuna) REFERENCES Comuna(id_comuna)
);

-- Socio
CREATE TABLE Socio (
    RUN VARCHAR(20) PRIMARY KEY,
    fecha_incorporacion DATE,
    estado VARCHAR(20),
    id_sucursal INT,
    FOREIGN KEY (RUN) REFERENCES Persona(RUN),
    FOREIGN KEY (id_sucursal) REFERENCES Sucursal(id_sucursal)
);

-- Invitado
CREATE TABLE Invitado (
    RUN VARCHAR(20) PRIMARY KEY,
    FOREIGN KEY (RUN) REFERENCES Persona(RUN)
);

-- Contacto
CREATE TABLE Contacto (
    RUN VARCHAR(20) PRIMARY KEY,
    FOREIGN KEY (RUN) REFERENCES Persona(RUN)
);

-- Usuario
CREATE TABLE Usuario (
    id_usuario INT PRIMARY KEY,
    RUN VARCHAR(20),
    correo VARCHAR(100),
    clave VARCHAR(100),
    tipo_usuario VARCHAR(50),
    FOREIGN KEY (RUN) REFERENCES Persona(RUN)
);

-- Empresa
CREATE TABLE Empresa (
    RUT_empresa VARCHAR(20) PRIMARY KEY,
    nombre VARCHAR(100)
);

-- Cliente
CREATE TABLE Cliente (
    id_cliente INT PRIMARY KEY
);

-- Lugar
CREATE TABLE Lugar (
    id_lugar INT PRIMARY KEY,
    tipo VARCHAR(50),
    capacidad INT,
    direccion_real VARCHAR(100),
    id_sucursal INT,
    FOREIGN KEY (id_sucursal) REFERENCES Sucursal(id_sucursal)
);

-- PrecioLugar
CREATE TABLE PrecioLugar (
    id_precio INT PRIMARY KEY,
    valor INT,
    fecha_inicio DATE,
    fecha_fin DATE,
    hora_inicio TIME,
    hora_fin TIME,
    tipo_cobro VARCHAR(20),
    id_lugar INT,
    FOREIGN KEY (id_lugar) REFERENCES Lugar(id_lugar)
);

-- Reserva
CREATE TABLE Reserva (
    id_reserva INT PRIMARY KEY,
    fecha DATE,
    hora_inicio TIME,
    hora_fin TIME,
    estado VARCHAR(20),
    id_socio VARCHAR(20),
    id_lugar INT,
    FOREIGN KEY (id_socio) REFERENCES Socio(RUN),
    FOREIGN KEY (id_lugar) REFERENCES Lugar(id_lugar)
);

-- Evento
CREATE TABLE Evento (
    id_evento INT PRIMARY KEY,
    nombre VARCHAR(100),
    fecha DATE,
    id_lugar INT,
    FOREIGN KEY (id_lugar) REFERENCES Lugar(id_lugar)
);

-- AsistenteEvento (entidad débil)
CREATE TABLE AsistenteEvento (
    id_evento INT,
    identificador VARCHAR(50),
    nombre VARCHAR(100),
    PRIMARY KEY (id_evento, identificador),
    FOREIGN KEY (id_evento) REFERENCES Evento(id_evento)
);

-- Membresia
CREATE TABLE Membresia (
    id_membresia INT PRIMARY KEY,
    fecha_inicio DATE,
    fecha_fin DATE,
    id_socio VARCHAR(20),
    FOREIGN KEY (id_socio) REFERENCES Socio(RUN)
);

-- Cuota
CREATE TABLE Cuota (
    id_cuota INT PRIMARY KEY,
    mes INT,
    anio INT,
    monto INT,
    fecha_limite DATE,
    id_membresia INT,
    FOREIGN KEY (id_membresia) REFERENCES Membresia(id_membresia)
);

-- Pago
CREATE TABLE Pago (
    id_pago INT PRIMARY KEY,
    monto INT,
    fecha DATE,
    tipo VARCHAR(20),
    id_cuota INT,
    id_reserva INT,
    id_evento INT,
    FOREIGN KEY (id_cuota) REFERENCES Cuota(id_cuota),
    FOREIGN KEY (id_reserva) REFERENCES Reserva(id_reserva),
    FOREIGN KEY (id_evento) REFERENCES Evento(id_evento)
);

-- Familiar
CREATE TABLE Familiar (
    id_familiar INT PRIMARY KEY,
    run_titular VARCHAR(20),
    run_persona VARCHAR(20),
    tipo VARCHAR(20),
    fecha_inicio DATE,
    fecha_fin DATE,
    FOREIGN KEY (run_titular) REFERENCES Socio(RUN),
    FOREIGN KEY (run_persona) REFERENCES Persona(RUN)
);

-- Cargo
CREATE TABLE Cargo (
    id_cargo INT PRIMARY KEY,
    nombre VARCHAR(50),
    fecha_inicio DATE,
    fecha_fin DATE,
    RUN VARCHAR(20),
    id_sucursal INT,
    FOREIGN KEY (RUN) REFERENCES Persona(RUN),
    FOREIGN KEY (id_sucursal) REFERENCES Sucursal(id_sucursal)
);