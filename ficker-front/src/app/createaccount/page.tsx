import Image from "next/image";
import styles from "./createaccount.module.scss";
import Link from "next/link";

const CreateAccountPage = () => {
  return (
    <div>
      <div style={{ background: "#fff", padding: 10, alignItems: "center" }}>
        <Image src="/logo.png" alt="Logo" width={130} height={27} />
      </div>
      <div className={styles.container}>
        <form className={styles.form}>
          <h3 style={{ textAlign: "center" }}>Cadastro</h3>
          <label htmlFor="name" style={{ marginBottom: 5 }}>
            Nome
          </label>
          <input type="text" id="name" required className={styles.input} />
          <label htmlFor="email" style={{ marginBottom: 5 }}>
            Email
          </label>
          <input type="text" id="email" required className={styles.input} />
          <label htmlFor="password" style={{ marginBottom: 5 }}>
            Senha
          </label>
          <input
            type="password"
            id="password"
            required
            className={styles.input}
          />
          <label htmlFor="password" style={{ marginBottom: 5 }}>
            Confirmar Senha
          </label>
          <input
            type="password"
            id="password"
            required
            className={styles.input}
          />
          <div
            style={{
              display: "flex",
              justifyContent: "center",
              flexDirection: "column",
              alignItems: "center",
            }}
          >
            <button type="submit" className={styles.button}>
              Cadastrar
            </button>
            <Link href={"/login"}>
              <p style={{ fontSize: 14, marginTop: 20 }}>Já possui cadastro?</p>
            </Link>
          </div>
        </form>
      </div>
    </div>
  );
};

export default CreateAccountPage;
