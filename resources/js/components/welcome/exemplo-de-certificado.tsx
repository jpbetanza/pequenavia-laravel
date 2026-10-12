export function ExemploDeCertificado() {
    return (
        <div className="relative h-[380px]">
            <div
                aria-hidden="true"
                className="absolute top-[26px] left-[14%] h-[320px] w-[64%] -rotate-[5deg] rounded-md border border-border bg-mesa"
            />
            <figure
                aria-label="Exemplo: certificado de batismo"
                className="absolute top-0 left-[10%] m-0 h-[350px] w-[70%] rounded-md bg-card p-3.5 shadow-[0_30px_60px_-30px_rgba(60,30,20,0.45)]"
            >
                <div className="h-full border-2 border-primary p-[5px]">
                    <div className="flex h-full flex-col items-center justify-center gap-2.5 border border-[#B8955A] px-5 py-5 text-center">
                        <p className="font-serif text-[22px] font-bold tracking-[0.14em] text-primary uppercase">
                            Certificado de Batismo
                        </p>
                        <p className="font-serif text-[15px] text-foreground/80 italic">
                            Certificamos que
                        </p>
                        <p
                            className="text-4xl leading-tight"
                            style={{ fontFamily: "'Pinyon Script', cursive" }}
                        >
                            Maria Clara Souza
                        </p>
                        <div className="h-px w-3/5 bg-[#B8955A]" />
                        <p className="max-w-[260px] font-serif text-sm text-foreground/80">
                            recebeu o Sacramento do Batismo em 17 de outubro de
                            2026.
                        </p>
                    </div>
                </div>
            </figure>
        </div>
    );
}
